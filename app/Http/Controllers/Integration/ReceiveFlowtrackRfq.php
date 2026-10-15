<?php

namespace App\Http\Controllers\Integration;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Accept only an explicit, signed, supplier-scoped RFQ projection. */
final class ReceiveFlowtrackRfq extends Controller
{
    use VerifiesFlowtrack;

    public function __invoke(Request $request): JsonResponse
    {
        $data = $this->signedData($request);
        if ($data === null) return response()->json(['message' => 'Unauthorized.'], 401);
        $validation = Validator::make($data, [
            'supplier_id' => ['required', 'integer', 'min:1'],
            'rfq' => ['required', 'array'],
            'rfq.invitation_id' => ['required', 'integer', 'min:1'],
            'rfq.reference' => ['required', 'string', 'max:120'],
            'rfq.inquiry_reference' => ['required', 'string', 'max:120'],
            'rfq.subject' => ['nullable', 'string', 'max:255'],
            'rfq.buyer_requirements' => ['nullable', 'string', 'max:8000'],
            'rfq.request_message' => ['nullable', 'string', 'max:2000'],
            'rfq.supplier_details' => ['nullable', 'string', 'max:2000'],
            'rfq.currency' => ['required', 'string', 'max:10'],
            'rfq.delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'rfq.due_at' => ['required', 'date'],
            'rfq.items' => ['required', 'array', 'min:1', 'max:100'],
            'rfq.items.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'rfq.items.*.product_code' => ['nullable', 'string', 'max:120'],
            'rfq.items.*.product_name' => ['required', 'string', 'max:255'],
            'rfq.items.*.category' => ['nullable', 'string', 'max:150'],
            'rfq.items.*.quantity' => ['required', 'numeric', 'gt:0', 'max:100000000'],
            'rfq.items.*.unit' => ['nullable', 'string', 'max:50'],
            'rfq.items.*.notes' => ['nullable', 'string', 'max:2000'],
            'rfq.documents' => ['present', 'array', 'max:100'],
            'rfq.documents.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'rfq.documents.*.name' => ['required', 'string', 'max:255'],
            'rfq.documents.*.size' => ['required', 'integer', 'min:0', 'max:20971520'],
            'rfq.documents.*.mime_type' => ['required', 'string', 'max:160'],
            'rfq.documents.*.version' => ['nullable', 'string', 'max:64'],
            'rfq.supplier_task' => ['nullable', 'array'],
            'rfq.supplier_task.id' => ['required_with:rfq.supplier_task', 'integer', 'min:1'],
            'rfq.supplier_task.title' => ['required_with:rfq.supplier_task', 'string', 'max:255'],
            'rfq.supplier_task.instructions' => ['nullable', 'string', 'max:4000'],
            'rfq.supplier_task.status' => ['required_with:rfq.supplier_task', 'string', 'max:100'],
            'rfq.supplier_task.completed' => ['required_with:rfq.supplier_task', 'boolean'],
            'rfq.supplier_task.requires_submission' => ['required_with:rfq.supplier_task', 'boolean'],
            'rfq.supplier_task.due_date' => ['nullable', 'date_format:Y-m-d'],
            'rfq.supplier_task.uploaded_files' => ['sometimes', 'array', 'max:10'],
            'rfq.supplier_task.uploaded_files.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'rfq.supplier_task.uploaded_files.*.name' => ['required', 'string', 'max:255'],
            'rfq.supplier_task.uploaded_files.*.mime_type' => ['required', 'string', 'max:160'],
            'rfq.supplier_task.uploaded_files.*.size' => ['required', 'integer', 'min:1', 'max:20971520'],
            'rfq.supplier_task.uploaded_files.*.version' => ['nullable', 'string', 'max:64'],
        ]);
        if ($validation->fails() || $data['rfq']['reference'] !== 'RFQ-'.str_pad((string) $data['rfq']['invitation_id'], 6, '0', STR_PAD_LEFT)) {
            return response()->json(['message' => 'Invalid RFQ projection.'], 422);
        }
        $company = SupplierCompany::query()->where('flowtrack_supplier_id', $data['supplier_id'])
            ->where('active', true)->first(['id']);
        if (! $company) return response()->json(['message' => 'Supplier not linked or inactive.'], 409);

        $warmDocuments = [];
        $resourceId = null;
        DB::transaction(function () use ($data, $company, &$warmDocuments, &$resourceId): void {
            $input = $data['rfq'];
            $record = SupplierResource::query()->firstOrNew([
                'supplier_company_id' => $company->id,
                'type' => 'rfq',
                'external_reference' => $input['reference'],
            ]);
            if (! $record->exists) $record->status = 'open';
            // A resend must not reset a supplier's draft, submission, or decline.
            $record->due_at = $input['due_at'];
            $record->payload = [
                'invitation_id' => (int) $input['invitation_id'],
                'title' => $input['subject'] ?: $input['inquiry_reference'],
                'inquiry_reference' => $input['inquiry_reference'],
                'buyer_label' => 'FlowTracker Buyer',
                'buyer_requirements' => $input['buyer_requirements'] ?? '',
                'request_message' => $input['request_message'] ?? '',
                'supplier_details' => $input['supplier_details'] ?? '',
                'currency' => $input['currency'],
                'delivery_date' => $input['delivery_date'] ?? null,
                'product_name' => count($input['items']) === 1 ? $input['items'][0]['product_name'] : count($input['items']).' requested products',
                'product_code' => count($input['items']) === 1 ? ($input['items'][0]['product_code'] ?? '') : '',
                'quantity' => array_sum(array_column($input['items'], 'quantity')),
                'items' => array_map(static fn (array $item): array => [
                    'id' => $item['id'], 'product_name' => $item['product_name'],
                    'product_code' => $item['product_code'] ?? '',
                    'category' => $item['category'] ?? '', 'quantity' => $item['quantity'],
                    'unit' => $item['unit'] ?? '', 'notes' => $item['notes'] ?? '',
                ], $input['items']),
                'supplier_task' => isset($input['supplier_task']) && $input['supplier_task']
                    ? $input['supplier_task'] + ['uploaded_files' => []]
                    : null,
                'documents' => array_map(static fn (array $doc): array => [
                    'id' => $doc['id'], 'name' => basename($doc['name']),
                    'mime_type' => $doc['mime_type'], 'size' => $doc['size'],
                    'version' => (string) ($doc['version'] ?? ''),
                ], $input['documents']),
            ];
            // Current FlowTracker documents are authoritative. Keep legacy local
            // receipts only for messages from older FlowTracker installations.
            if (! array_key_exists('uploaded_files', $input['supplier_task'] ?? [])) {
                $oldPayload = $record->exists ? ($record->getRawOriginal('payload') ? json_decode((string) $record->getRawOriginal('payload'), true) : []) : [];
                $payload = $record->payload;
                if (isset($payload['supplier_task']['id'])
                    && (int) $payload['supplier_task']['id'] === (int) ($oldPayload['supplier_task']['id'] ?? 0)) {
                    $payload['supplier_task']['uploaded_files'] = $oldPayload['supplier_task']['uploaded_files'] ?? [];
                    $record->payload = $payload;
                }
            }
            if (! $record->exists || $record->isDirty(['status', 'due_at', 'payload'])) {
                $record->save();
                $company->newQuery()->whereKey($company->id)->increment('dashboard_revision');
            }
            $resourceId = (int) $record->id;
            // Bounded eager cache; larger documents are still available via signed on-demand fetch.
            $warmDocuments = collect($input['documents'])->filter(fn (array $doc): bool =>
                (int) $doc['size'] > 0 && (int) $doc['size'] <= 20971520,
            )->take(20)->map(fn (array $doc): array => [
                'id' => (int) $doc['id'], 'version' => (string) ($doc['version'] ?? ''),
            ])->all();
        });
        if (config('queue.default') !== 'sync' && $resourceId) {
            foreach ($warmDocuments as $doc) {
                try {
                    \App\Jobs\WarmBuyerRfqFile::dispatch($resourceId, $doc['id'], $doc['version'])
                        ->onQueue('supplier-rfq-files');
                } catch (\Throwable) {
                    // The signed on-demand document route remains available.
                    \Illuminate\Support\Facades\Log::warning('RFQ document prefetch deferred.', ['resource_id' => $resourceId]);
                }
            }
        }
        return response()->json(['received' => true]);
    }
}
