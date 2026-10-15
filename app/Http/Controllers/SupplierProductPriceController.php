<?php

namespace App\Http\Controllers;

use App\Models\SupplierPriceProposal;
use App\Models\SupplierResource;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SupplierProductPriceController extends Controller
{
    public function index(Request $request)
    {
        $company = $request->user()->company;
        $companyId = (int) $company->id;
        $search = mb_substr(trim((string) $request->query('q', '')), 0, 100);
        $productStatus = in_array($request->query('product_status'), ['active', 'inactive'], true)
            ? $request->query('product_status') : '';
        $requestStatus = in_array($request->query('request_status'), ['none', 'pending', 'approved', 'rejected', 'stale'], true)
            ? $request->query('request_status') : '';

        $query = SupplierResource::query()->where('supplier_company_id', $companyId)
            ->where('type', 'product')->whereIn('status', ['active', 'inactive']);
        if ($productStatus !== '') {
            $query->where('status', $productStatus);
        }
        if ($search !== '') {
            $term = '%'.addcslashes($search, '%_\\').'%';
            $query->where(function ($match) use ($term): void {
                $match->where('external_reference', 'like', $term)
                    ->orWhere('payload->name', 'like', $term)
                    ->orWhere('payload->code', 'like', $term);
            });
        }
        if ($requestStatus !== '') {
            $latestStatus = SupplierPriceProposal::query()->select('status')
                ->where('supplier_company_id', $companyId)
                ->whereColumn('supplier_resource_id', 'supplier_resources.id')
                ->orderByDesc('id')->limit(1);
            if ($requestStatus === 'none') {
                $query->whereNotExists(function ($proposal) use ($companyId): void {
                    $proposal->selectRaw('1')->from('supplier_price_proposals')
                        ->where('supplier_company_id', $companyId)
                        ->whereColumn('supplier_resource_id', 'supplier_resources.id');
                });
            } else {
                $query->where($latestStatus, $requestStatus);
            }
        }

        $products = $query->orderBy('id')->paginate(8, ['id', 'external_reference', 'status', 'payload', 'updated_at']);
        $latestIds = SupplierPriceProposal::query()->where('supplier_company_id', $companyId)
            ->whereIn('supplier_resource_id', $products->pluck('id'))
            ->selectRaw('MAX(id)')->groupBy('supplier_resource_id');
        $proposals = SupplierPriceProposal::query()->whereIn('id', $latestIds)
            ->get(['id', 'supplier_resource_id', 'status', 'created_at', 'updated_at'])
            ->keyBy('supplier_resource_id');

        $totalProducts = ($search === '' && $productStatus === '' && $requestStatus === '')
            ? $products->total()
            : SupplierResource::query()->where('supplier_company_id', $companyId)->where('type', 'product')
                ->whereIn('status', ['active', 'inactive'])->count();
        $requestCounts = SupplierPriceProposal::query()->where('supplier_company_id', $companyId)
            ->selectRaw("COUNT(DISTINCT CASE WHEN status = 'pending' THEN supplier_resource_id END) AS pending_count")
            ->selectRaw("SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS approved_count")
            ->first();

        return view('portal.product-prices', compact(
            'products', 'proposals', 'company', 'search', 'productStatus', 'requestStatus',
            'totalProducts', 'requestCounts'
        ));
    }

    public function show(Request $request, int $id)
    {
        $company = $request->user()->company;
        $product = SupplierResource::query()->where('supplier_company_id', $company->id)
            ->where('type', 'product')->whereIn('status', ['active', 'inactive'])->findOrFail($id);
        $latest = SupplierPriceProposal::query()->where('supplier_company_id', $company->id)
            ->where('supplier_resource_id', $product->id)->latest('id')->first();
        return view('portal.product-price-show', compact('company', 'product', 'latest'));
    }

    public function edit(Request $request, int $id)
    {
        $company = $request->user()->company;
        $product = SupplierResource::query()->where('supplier_company_id', $company->id)
            ->where('type', 'product')->where('status', 'active')->findOrFail($id);
        $latest = SupplierPriceProposal::query()->where('supplier_company_id', $company->id)
            ->where('supplier_resource_id', $product->id)->latest('id')->first();
        return view('portal.product-price-edit', compact('company', 'product', 'latest'));
    }

    public function submit(Request $request, int $id): RedirectResponse
    {
        if ($request->exists('tiers')) {
            // The prototype's row editor sends the same quantity/price pairs as the original textarea.
            $data = $request->validate([
                'tiers' => ['required', 'array', 'min:1', 'max:100'],
                'tiers.*.quantity' => ['required', 'integer', 'min:1', 'max:100000000'],
                'tiers.*.price' => ['required', 'regex:/^\d+(?:\.\d{1,6})?$/'],
            ]);
            $lines = array_map(static fn (array $tier): string => $tier['quantity'].' '.$tier['price'], $data['tiers']);
            $rows = $this->parseRows(implode("\n", $lines));
        } else {
            // Keep compatibility with existing integrations/requests using the old text format.
            $data = $request->validate(['prices' => ['required', 'string', 'max:10000']]);
            $rows = $this->parseRows($data['prices']);
        }
        $companyId = (int) $request->user()->supplier_company_id;
        $proposalId = DB::transaction(function () use ($id, $request, $companyId, $rows): int {
            $product = SupplierResource::query()->where('supplier_company_id', $companyId)
                ->where('type', 'product')->where('status', 'active')->whereKey($id)
                ->lockForUpdate()->firstOrFail();
            $version = $product->payload['price_version'] ?? null;
            if (! is_string($version) || ! preg_match('/^[a-f0-9]{64}$/', $version)) {
                throw ValidationException::withMessages(['prices' => 'Price baseline is not synchronized yet.']);
            }
            if (SupplierPriceProposal::query()->where('supplier_company_id', $companyId)
                ->where('supplier_resource_id', $product->id)->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['prices' => 'This product already has a pending price request.']);
            }
            $proposal = SupplierPriceProposal::query()->create([
                'supplier_company_id' => $companyId, 'supplier_resource_id' => $product->id,
                'supplier_user_id' => $request->user()->id, 'request_uuid' => (string) Str::uuid(),
                'base_version' => $version, 'proposed_prices' => $rows,
                'status' => 'pending', 'delivery_status' => 'pending',
            ]);
            return (int) $proposal->id;
        });
        try { \App\Jobs\DeliverSupplierPriceProposal::dispatch($proposalId)->onQueue('default'); }
        catch (\Throwable $exception) { report($exception); }
        return redirect()->route('supplier.products.prices.edit', $id)->with('success',
            'Price request saved. Delivery to FlowTracker is queued for approval; approved prices remain unchanged.');
    }

    private function parseRows(string $value): array
    {
        $result = [];
        foreach (preg_split('/\R/', trim($value)) ?: [] as $line) {
            $line = trim($line);
            if ($line === '') continue;
            if (preg_match('/^quantity[\s,;|]+(?:unit\s+)?price$/i', $line)) continue;
            if (! preg_match('/^(\d+)[\t ,;|]+(\d+(?:\.\d{1,6})?)$/', $line, $matches)) {
                throw ValidationException::withMessages(['prices' => 'Enter one quantity and price per row, e.g. 100  2.50.']);
            }
            $quantity = (int) $matches[1];
            $price = (float) $matches[2];
            if ($quantity < 1 || $quantity > 100000000 || $price > 999999999.99 || isset($result[$quantity])) {
                throw ValidationException::withMessages(['prices' => 'Invalid or duplicate quantity/price tier.']);
            }
            $result[$quantity] = ['quantity' => $quantity, 'price' => $price];
            if (count($result) > 100) throw ValidationException::withMessages(['prices' => 'Maximum 100 tiers per product.']);
        }
        if ($result === []) throw ValidationException::withMessages(['prices' => 'Enter at least one price tier.']);
        ksort($result, SORT_NUMERIC);
        return array_values($result);
    }
}
