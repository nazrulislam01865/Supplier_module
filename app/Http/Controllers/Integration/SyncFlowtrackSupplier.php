<?php

namespace App\Http\Controllers\Integration;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierPriceProposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** FlowTracker is the sole authority for supplier identity and product assignments. */
final class SyncFlowtrackSupplier extends Controller
{
    use VerifiesFlowtrack;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $this->signedData($request);
        if ($data === null) return response()->json(['message' => 'Unauthorized.'], 401);
        $validator = Validator::make($data, [
            'supplier_id' => ['required', 'integer', 'min:1'],
            'catalog_revision' => ['required', 'integer', 'min:1'],
            'reference' => ['required', 'string', 'max:80'],
            'name' => ['required', 'string', 'max:255'],
            'short_code' => ['required', 'string', 'max:40'],
            'brand_name' => ['sometimes', 'string', 'max:255'],
            'footer' => ['sometimes', 'array:copyright,links'],
            'footer.copyright' => ['present', 'nullable', 'string', 'max:255'],
            'footer.links' => ['required_with:footer', 'array:privacy,terms,help'],
            'footer.links.*' => ['required', 'array:enabled,label,url'],
            'footer.links.*.enabled' => ['required', 'boolean'],
            'footer.links.*.label' => ['required', 'string', 'max:80'],
            'footer.links.*.url' => ['nullable', 'string', 'max:2048', 'url:http,https'],
            'brand_logo_path' => ['sometimes', 'nullable', 'regex:#^/branding-assets/logo/[A-Za-z0-9_-]+\.(?:png|jpe?g|webp)(?:/workspace/[1-9][0-9]*)?$#', 'max:2048'],
            'brand_favicon_path' => ['sometimes', 'nullable', 'regex:#^/branding-assets/favicon/[A-Za-z0-9_-]+\.(?:ico|png|jpe?g|webp)(?:/workspace/[1-9][0-9]*)?$#', 'max:2048'],
            'active' => ['required', 'boolean'],
            'products' => ['present', 'array', 'max:2000'],
            'products.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'products.*.code' => ['required', 'string', 'max:120'],
            'products.*.name' => ['required', 'string', 'max:255'],
            'products.*.active' => ['required', 'boolean'],
            'products.*.supplier_prices' => ['present', 'array', 'max:100'],
            'products.*.supplier_prices.*.quantity' => ['required', 'integer', 'min:1'],
            'products.*.supplier_prices.*.price' => ['required', 'numeric', 'min:0'],
            'products.*.price_version' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'products.*.image_url' => ['sometimes', 'nullable', 'url:http,https', 'max:2048'],
            'products.*.image_version' => ['sometimes', 'nullable', 'regex:/^[a-f0-9]{64}$/'],
            'products.*.image_extension' => ['sometimes', 'nullable', 'in:png,jpg,jpeg,webp'],
            'decisions' => ['sometimes', 'array', 'max:500'],
            'decisions.*.request_uuid' => ['required', 'uuid'],
            'decisions.*.status' => ['required', 'in:approved,rejected,stale'],
            'decisions.*.review_note' => ['nullable', 'string', 'max:500'],
        ]);
        if ($validator->fails()) {
            // Signed integration callers may inspect invalid field paths, never the submitted values.
            return response()->json([
                'message' => 'Invalid supplier catalogue.',
                'invalid_fields' => array_slice($validator->errors()->keys(), 0, 20),
            ], 422);
        }

        $id = (int) $data['supplier_id'];
        $result = DB::transaction(function () use ($id, $data): SupplierCompany {
            $company = SupplierCompany::query()->where('flowtrack_supplier_id', $id)->lockForUpdate()->first();
            if (! $company) {
                // Never implicitly take over an existing, independently invited account.
                $company = SupplierCompany::create([
                    'flowtrack_supplier_id' => $id, 'reference' => $data['reference'],
                    'name' => $data['name'], 'flowtrack_short_code' => $data['short_code'], 'active' => $data['active'],
                ]);
            }
            if ((int) $data['catalog_revision'] < (int) ($company->catalog_revision ?? 0)) {
                return $company; // Ignore out-of-order catalogue deliveries.
            }
            // A delayed full-catalogue request must not revert newer fast branding.
            $fields = ['name' => $data['name'], 'active' => $data['active'],
                'flowtrack_short_code' => $data['short_code']];
            if ((int) $data['catalog_revision'] >= (int) ($company->brand_revision ?? 0)) {
                $fields['flowtrack_brand_name'] = $data['brand_name'] ?? $company->flowtrack_brand_name;
                if (array_key_exists('footer', $data)) $fields['flowtrack_portal_footer'] = \App\Support\SupplierPortalFooter::normalize($data['footer']);
                $fields['flowtrack_brand_logo_url'] = array_key_exists('brand_logo_path', $data)
                    ? \App\Support\TrustedFlowtrackBrandUrl::resolve($data['brand_logo_path']) : $company->flowtrack_brand_logo_url;
                if (array_key_exists('brand_favicon_path', $data)) {
                    $favicon = \App\Support\TrustedFlowtrackBrandUrl::favicon($data['brand_favicon_path']);
                    $fields['flowtrack_brand_favicon_url'] = $favicon;
                }
                $company->brand_revision = (int) $data['catalog_revision'];
            }
            $company->fill($fields);
            if ($company->isDirty(['name', 'active', 'flowtrack_brand_name', 'flowtrack_brand_logo_url', 'flowtrack_brand_favicon_url', 'flowtrack_portal_footer'])) {
                $company->dashboard_revision = ((int) $company->dashboard_revision) + 1;
            }
            $company->catalog_revision = (int) $data['catalog_revision'];
            $company->save();
            $now = now();
            $rows = [];
            $references = [];
            // One batched read: don't rewrite unchanged prices/images or move 'last updated' on every reconciliation.
            $existing = SupplierResource::query()->where('supplier_company_id', $company->id)
                ->where('type', 'product')->get(['external_reference', 'status', 'payload'])
                ->keyBy('external_reference');
            foreach ($data['products'] as $product) {
                $ref = 'FT-P-'.$product['id'];
                $references[] = $ref;
                $payload = [
                    'code' => $product['code'], 'name' => $product['name'],
                    'supplier_prices' => $product['supplier_prices'], 'price_version' => $product['price_version'],
                    'image_url' => null, 'image_version' => $product['image_version'] ?? null,
                    'image_extension' => $product['image_extension'] ?? null,
                ];
                $status = $product['active'] ? 'active' : 'inactive';
                $old = $existing->get($ref);
                if ($old && $old->status === $status && $old->payload == $payload) continue;
                $rows[] = [
                    'supplier_company_id' => $company->id, 'type' => 'product',
                    'external_reference' => $ref,
                    'status' => $status,
                    'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
                    'created_at' => $now, 'updated_at' => $now,
                ];
            }
            foreach (array_chunk($rows, 250) as $chunk) {
                SupplierResource::query()->upsert($chunk, ['supplier_company_id', 'type', 'external_reference'], ['status', 'payload', 'updated_at']);
            }
            $stale = SupplierResource::query()->where('supplier_company_id', $company->id)
                ->where('type', 'product')->where('external_reference', 'like', 'FT-P-%');
            if ($references !== []) $stale->whereNotIn('external_reference', $references);
            // Keep proposal audit when FlowTracker removes a product assignment.
            $staleIds = (clone $stale)->pluck('id');
            if ($staleIds->isNotEmpty()) {
                SupplierPriceProposal::query()->where('supplier_company_id', $company->id)
                    ->whereIn('supplier_resource_id', $staleIds)->where('status', 'pending')
                    ->update(['status' => 'stale', 'review_note' => 'Product assignment was removed.']);
            }
            $stale->delete();
            foreach ($data['decisions'] ?? [] as $decision) {
                SupplierPriceProposal::query()->where('supplier_company_id', $company->id)
                    ->where('request_uuid', $decision['request_uuid'])->where('status', 'pending')
                    ->update(['status' => $decision['status'], 'delivery_status' => 'delivered',
                        'review_note' => $decision['review_note'] ?? null]);
            }
            return $company;
        });
        // One lightweight dispatch per catalogue, not one queue insert per image in the API request.
        // A five-minute repair command also discovers missing photos if this enqueue fails.
        if (config('queue.default') !== 'sync') {
            try { \App\Jobs\WarmSupplierProductImages::dispatch((int) $result->id)->onQueue('default'); }
            catch (\Throwable $exception) { report($exception); }
        }
        return response()->json(['synced' => true, 'supplier_id' => $id, 'company_id' => $result->id]);
    }
}
