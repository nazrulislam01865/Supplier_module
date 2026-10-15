<?php

namespace App\Http\Controllers\Integration;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/** Stateless machine-only endpoint. Neither supplier browser sessions nor FlowTracker DB are used. */
final class ReceiveFlowtrackOrder extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('flowtrack_bridge.secret');
        if (! config('flowtrack_bridge.enabled') || ! is_string($secret) || strlen($secret) < 32) {
            return response()->json(['message' => 'Not found.'], 404);
        }
        $body = $request->getContent();
        $time = $request->header('X-FlowTrack-Timestamp');
        $event = $request->header('X-FlowTrack-Event');
        $signature = $request->header('X-FlowTrack-Signature');
        if (strlen($body) > 1048576 || ! is_string($time) || ! ctype_digit($time) ||
            abs(time() - (int) $time) > 300 || ! is_string($event) ||
            ! preg_match('/^[0-9a-f-]{36}$/i', $event) || ! is_string($signature) ||
            ! preg_match('/^[0-9a-f]{64}$/i', $signature) ||
            ! hash_equals(hash_hmac('sha256', $time."\n".$event."\n".$body, $secret), $signature)) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $data = json_decode($body, true);
        if (! is_array($data)) {
            return response()->json(['message' => 'Invalid data.'], 422);
        }
        $validator = Validator::make($data, [
            'event_id' => ['required', 'uuid', 'in:'.$event],
            'supplier_id' => ['required', 'integer', 'min:1'],
            'order' => ['required', 'array'],
            'order.reference' => ['required', 'string', 'max:120'],
            'order.revoked' => ['sometimes', 'boolean'],
            'order.revision' => ['sometimes', 'integer', 'min:0'],
            'order.source_id' => ['sometimes', 'integer', 'min:1'],
            'order.title' => ['required_unless:order.revoked,true', 'string', 'max:255'],
            'order.created_at' => ['nullable', 'date'],
            'order.status' => ['sometimes', 'string', 'max:80'],
            'order.currency' => ['sometimes', 'string', 'regex:/^[A-Z]{3}$/'],
            'order.delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'order.supplier_ship_date' => ['nullable', 'date_format:Y-m-d'],
            'order.estimated_delivery_date' => ['nullable', 'date_format:Y-m-d'],
            'order.supplier_instruction' => ['nullable', 'string', 'max:2000'],
            'order.items' => ['required_unless:order.revoked,true', 'array', 'min:1', 'max:100'],
            'order.workflow' => ['sometimes', 'array'],
            'order.workflow.current_phase_id' => ['required_with:order.workflow', 'integer', 'min:0'],
            'order.workflow.stages' => ['required_with:order.workflow', 'array', 'max:7'],
            'order.workflow.stages.*.id' => ['required', 'integer', 'min:1'],
            'order.workflow.stages.*.sequence' => ['required', 'integer', 'min:1', 'max:20'],
            'order.workflow.stages.*.name' => ['required', 'string', 'max:100'],
            'order.workflow.stages.*.color' => ['required', 'string', 'regex:/^#[0-9a-f]{6}$/i'],
            'order.workflow.stages.*.state' => ['required', 'string', 'in:active,completed,locked,cancelled'],
            'order.workflow.stages.*.state_label' => ['required', 'string', 'max:120'],
            'order.workflow.stages.*.dependency' => ['required', 'string', 'max:120'],
            'order.workflow.stages.*.owner' => ['required', 'string', 'max:255'],
            'order.workflow.stages.*.progress' => ['required', 'integer', 'min:0', 'max:100'],
            'order.workflow.stages.*.required_complete' => ['required', 'integer', 'min:0'],
            'order.workflow.stages.*.required_total' => ['required', 'integer', 'min:0'],
            'order.workflow.stages.*.tasks' => ['required', 'array', 'max:60'],
            'order.workflow.stages.*.tasks.*.id' => ['required', 'integer', 'min:1'],
            'order.workflow.stages.*.tasks.*.code' => ['required', 'string', 'max:40'],
            'order.workflow.stages.*.tasks.*.title' => ['required', 'string', 'max:255'],
            'order.workflow.stages.*.tasks.*.status' => ['required', 'string', 'max:100'],
            'order.workflow.stages.*.tasks.*.mode' => ['required', 'string', 'in:active,done,locked'],
            'order.workflow.stages.*.tasks.*.required' => ['required', 'boolean'],
            'order.workflow.stages.*.tasks.*.assignee' => ['required', 'string', 'max:255'],
            'order.workflow.stages.*.tasks.*.assigned_to_supplier' => ['required', 'boolean'],
            'order.workflow.stages.*.tasks.*.due_date' => ['nullable', 'date_format:Y-m-d'],
            'order.workflow.stages.*.tasks.*.can_act' => ['required', 'boolean'],
            'order.workflow.stages.*.tasks.*.action_key' => ['nullable', 'string', 'max:50'],
            'order.workflow.stages.*.tasks.*.artwork_files' => ['sometimes', 'array', 'max:20'],
            'order.workflow.stages.*.tasks.*.artwork_files.*.id' => ['required', 'integer', 'min:1'],
            'order.workflow.stages.*.tasks.*.artwork_files.*.name' => ['required', 'string', 'max:255'],
            'order.workflow.stages.*.tasks.*.revision_sources' => ['sometimes', 'array', 'max:20'],
            'order.workflow.stages.*.tasks.*.revision_sources.*.id' => ['required', 'integer', 'min:1'],
            'order.workflow.stages.*.tasks.*.revision_sources.*.name' => ['required', 'string', 'max:255'],
            'order.stages' => ['sometimes' , 'array', 'size:3'],
            'order.stages.*.name' => ['required', 'string', 'in:Artwork,Production,Quality control'],
            'order.stages.*.status' => ['required', 'string', 'in:completed,in_progress,upcoming'],
            'order.stages.*.tasks' => ['present', 'array', 'max:8'],
            'order.stages.*.updated_at' => ['nullable', 'date'],
            'order.stages.*.tasks.*.name' => ['required', 'string', 'max:80'],
            'order.stages.*.tasks.*.status' => ['required', 'string', 'in:completed,pending,upcoming,in_progress,skipped'],
            'order.stages.*.tasks.*.updated_at' => ['nullable', 'date'],
            'order.documents' => ['sometimes', 'array', 'max:20'],
            'order.documents.*.id' => ['required', 'integer', 'min:1', 'distinct'],
            'order.documents.*.name' => ['required', 'string', 'max:255'],
            'order.documents.*.size' => ['required', 'integer', 'min:1', 'max:419430400'],
            'order.documents.*.version' => ['sometimes', 'integer', 'min:1', 'max:65535'],
            'order.documents.*.uploaded_at' => ['nullable', 'date'],
            'order.recent_updates' => ['sometimes', 'array', 'max:9'],
            'order.recent_updates.*.title' => ['required', 'string', 'max:150'],
            'order.recent_updates.*.status' => ['required', 'string', 'in:completed,in_progress,skipped,created'],
            'order.recent_updates.*.date' => ['nullable', 'date'],
            'order.items.*.product_name' => ['required', 'string', 'max:255'],
            'order.items.*.product_code' => ['sometimes', 'string', 'max:120'],
            'order.items.*.quantity' => ['required', 'integer', 'min:1'],
            'order.items.*.unit_price' => ['nullable', 'numeric', 'min:0', 'max:999999999999.99'],
            'order.items.*.notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Invalid order projection.'], 422);
        }

        $company = SupplierCompany::query()->where('flowtrack_supplier_id', $data['supplier_id'])
            ->where('active', true)->first(['id']);
        if (! $company) {
            return response()->json(['message' => 'Supplier not linked or inactive.'], 409);
        }

        // Monotonic revision plus row lock prevents out-of-order deliveries restoring stale status/files.
        $applied = DB::transaction(function () use ($company, $data): bool {
            $order = $data['order'];
            $resource = SupplierResource::query()->where('supplier_company_id', $company->id)
                ->where('type', 'order')->where('external_reference', $order['reference'])
                ->lockForUpdate()->first();
            $revision = (int) ($order['revision'] ?? 0);
            if ($resource && $revision < (int) ($resource->payload['revision'] ?? 0)) return false;
            if (! $resource) {
                $resource = new SupplierResource([
                    'supplier_company_id' => $company->id, 'type' => 'order',
                    'external_reference' => $order['reference'],
                ]);
            }
            if ($order['revoked'] ?? false) {
                $resource->status = 'revoked';
                $resource->due_at = null;
                $resource->payload = ['revision' => $revision, 'revoked' => true];
            } else {
                $previousPayload = $resource?->payload ?? [];
                if (! $resource->exists || $resource->status === 'revoked') $resource->status = 'new';
                $resource->due_at = $order['delivery_date'] ?? null;
                $resource->payload = [
                    'revision' => $revision,
                    'source_id' => (int) ($order['source_id'] ?? 0),
                    'title' => $order['title'] ?? ('Order '.$order['reference']),
                    'created_at' => $order['created_at'] ?? null,
                    'delivery_date' => $order['delivery_date'] ?? null,
                    'supplier_ship_date' => $order['supplier_ship_date'] ?? null,
                    'estimated_delivery_date' => $order['estimated_delivery_date'] ?? null,
                    'order_status' => $order['status'] ?? 'New',
                    'currency' => $order['currency'] ?? 'USD',
                    'supplier_instruction' => $order['supplier_instruction'] ?? '',
                    'items' => array_map(static fn (array $item): array => [
                        'product_name' => $item['product_name'],
                        'product_code' => $item['product_code'] ?? '',
                        'quantity' => (int) $item['quantity'],
                        'unit_price' => isset($item['unit_price']) ? (float) $item['unit_price'] : null,
                        'notes' => $item['notes'] ?? '',
                    ], $order['items']),
                    'workflow' => $order['workflow'] ?? ($previousPayload['workflow'] ?? []),
                    'stages' => array_map(static fn (array $stage): array => [
                        'name' => $stage['name'], 'status' => $stage['status'],
                        'updated_at' => $stage['updated_at'] ?? null,
                        'tasks' => array_map(static fn (array $task): array => [
                            'name' => $task['name'], 'status' => $task['status'],
                            'updated_at' => $task['updated_at'] ?? null,
                        ], $stage['tasks']),
                    ], $order['stages'] ?? []),
                    'documents' => array_map(static fn (array $file): array => [
                        'id' => (int) $file['id'],
                        'name' => basename(str_replace('\\', '/', $file['name'])),
                        'size' => (int) $file['size'],
                        'version' => isset($file['version']) ? (int) $file['version'] : null,
                        'uploaded_at' => $file['uploaded_at'] ?? null,
                    ], $order['documents'] ?? []),
                    'recent_updates' => $this->mergeActivity(
                        $previousPayload, $order['stages'] ?? [], $order['recent_updates'] ?? [],
                        (string) ($order['status'] ?? 'New'),
                    ),
                ];
            }
            if ($resource->exists && ! $resource->isDirty(['status', 'due_at', 'payload'])) return true;
            $resource->save();
            $company->newQuery()->whereKey($company->id)->increment('dashboard_revision');
            return true;
        });

        return response()->json(['received' => true, 'applied' => $applied]);
    }

    /** Preserve witnessed stage/task transitions even if the buyer's legacy audit contains no event. */
    private function mergeActivity(array $previous, array $newStages, array $sourceEvents, string $newStatus): array
    {
        $events = [];
        $observedAt = now()->toIso8601String();
        if (isset($previous['order_status']) && $previous['order_status'] !== $newStatus) {
            $events[] = [
                'title' => 'Order status · '.mb_substr($newStatus, 0, 80),
                'status' => strcasecmp($newStatus, 'Completed') === 0 ? 'completed' : 'in_progress',
                'date' => $observedAt,
            ];
        }
        $oldStages = collect($previous['stages'] ?? [])->keyBy('name');
        foreach ($newStages as $stage) {
            $old = $oldStages->get($stage['name']);
            if (! is_array($old)) continue;
            if ($stage['status'] !== ($old['status'] ?? null)) {
                $events[] = [
                    'title' => $stage['name'].' · '.ucwords(str_replace('_', ' ', $stage['status'])),
                    'status' => $stage['status'] === 'upcoming' ? 'created' : $stage['status'],
                    'date' => $observedAt,
                ];
            }
            $oldTasks = collect($old['tasks'] ?? [])->keyBy('name');
            foreach ($stage['tasks'] as $task) {
                $previousTask = $oldTasks->get($task['name']);
                if (! is_array($previousTask) || $task['status'] === ($previousTask['status'] ?? null)) continue;
                $events[] = [
                    'title' => $task['name'].' · '.ucwords(str_replace('_', ' ', $task['status'])),
                    'status' => in_array($task['status'], ['completed', 'skipped'], true)
                        ? $task['status'] : 'in_progress',
                    'date' => $observedAt,
                ];
            }
        }

        $events = array_merge($events, $sourceEvents, $previous['recent_updates'] ?? []);
        $unique = [];
        foreach ($events as $entry) {
            $key = $entry['title'].'|'.($entry['date'] ?? '');
            if (! isset($unique[$key])) $unique[$key] = $entry;
        }
        $unique = array_values($unique);
        usort($unique, static fn (array $a, array $b): int => strtotime($b['date'] ?? '') <=> strtotime($a['date'] ?? ''));
        return array_slice($unique, 0, 9);
    }
}
