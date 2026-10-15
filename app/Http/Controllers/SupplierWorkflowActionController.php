<?php

namespace App\Http\Controllers;

use App\Services\SupplierScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

/** Browser is authenticated locally; signed workflow intent travels only server-to-server. */
final class SupplierWorkflowActionController extends Controller
{
    public function __invoke(Request $request, int $id, int $task, SupplierScope $scope)
    {
        $order = $scope->resource($request, $id, 'order');
        abort_if($order->status === 'revoked', 404);
        $company = $request->user()->company;
        abort_unless($company && $company->active && (int) $company->flowtrack_supplier_id > 0, 403);
        $snapshot = $order->payload ?? [];
        $taskData = null;
        $stageName = '';
        foreach ($snapshot['workflow']['stages'] ?? [] as $stage) {
            foreach ($stage['tasks'] ?? [] as $entry) {
                if ((int) ($entry['id'] ?? 0) === $task) {
                    $taskData = $entry;
                    $stageName = (string) ($stage['name'] ?? 'Order workflow');
                    break 2;
                }
            }
        }
        abort_unless($taskData && ($taskData['can_act'] ?? false) && ($taskData['assigned_to_supplier'] ?? false), 403);
        $key = (string) ($taskData['action_key'] ?? '');
        // Old browser snapshots must not regain buyer-only date-setting actions.
        abort_if($key === 'PROD_SET_ESTIMATED_DELIVERY', 403);
        foreach (['estimated_delivery_date', 'supplier_delivery_date', 'shipment_date'] as $dateField) {
            abort_if($request->exists($dateField), 403);
        }
        $documentKeys = ['NEW_UPLOAD_PO', 'ART_PREPARE_UPLOAD', 'ART_SAMPLE_APPROVAL'];
        // Only assigned, actionable task rows may submit. The buyer repeats
        // these checks against its live DB before any task or document write.
        $isDocumentTask = in_array($key, $documentKeys, true);
        $maxKb = in_array($key, ['ART_PREPARE_UPLOAD', 'ART_SAMPLE_APPROVAL'], true) ? 409600 : 20480;
        $allowedExtensions = ['pdf','doc','docx','xls','xlsx','jpg','jpeg','png','webp','gif','ico','zip','txt','csv','ai','eps','esp','cdr'];
        $validated = $request->validate([
            'decision' => ['required', 'string', 'max:30'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'qty_received' => ['nullable', 'integer', 'min:0'],
            'qty_inspected' => ['nullable', 'integer', 'min:0'],
            'qty_accepted' => ['nullable', 'integer', 'min:0'],
            'qty_rejected' => ['nullable', 'integer', 'min:0'],
            'issue_category' => ['nullable', 'string', 'max:100'],
            'erp_reference' => ['nullable', 'string', 'max:150'],
            'carrier' => ['nullable', 'string', 'max:150'],
            'tracking_number' => ['nullable', 'string', 'max:150'],
            'client_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['nullable', 'string', 'max:255'],
            'phone_country_code' => ['nullable', 'string', 'max:50'],
            'phone_number' => ['nullable', 'string', 'max:70'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:150'],
            'state' => ['nullable', 'string', 'max:150'],
            'country' => ['nullable', 'string', 'max:150'],
            'postal_code' => ['nullable', 'string', 'max:40'],
            'revision_document_ids' => ['sometimes', 'array', 'max:20'],
            'revision_document_ids.*' => ['integer', 'min:1', 'distinct'],
            'revision_notes' => ['sometimes', 'array', 'max:20'],
            'revision_notes.*' => ['nullable', 'string', 'max:2000'],
            'files' => [$isDocumentTask ? 'required' : 'sometimes', 'array', 'max:5'],
            'files.*' => ['file', 'max:'.$maxKb, function ($attribute, $value, $fail) use ($allowedExtensions) {
                if (! in_array(strtolower((string) $value->getClientOriginalExtension()), $allowedExtensions, true)) {
                    $fail('Unsupported task attachment format.');
                }
            }],
        ]);
        // Match FlowTracker's two-step client-artwork decision. Submitting
        // ERP upload does not approve the client's artwork or complete the task.
        if ($key === 'ART_CLIENT_ERP_DECISION') {
            $status = strtolower((string) ($taskData['status'] ?? ''));
            $waitingOnClient = str_contains($status, 'waiting for client')
                || str_contains($status, 'client approval');
            $allowed = $waitingOnClient ? ['confirm', 'sample', 'revise'] : ['erp_uploaded'];
            if (! in_array($validated['decision'], $allowed, true)) {
                throw ValidationException::withMessages(['workflow' => 'This client decision is not available in the current task state. Refresh the order.']);
            }
        }
        $suppliedFiles = $request->file('files', []);
        $suppliedFiles = is_array($suppliedFiles) ? $suppliedFiles : [];
        $files = array_values($suppliedFiles);
        $sourceIds = [];
        if ($isDocumentTask && $key === 'ART_PREPARE_UPLOAD' && !empty($taskData['revision_sources'])) {
            $sourceIds = array_map('intval', array_keys($suppliedFiles));
            $expectedIds = array_map(static fn ($row): int => (int) $row['id'], $taskData['revision_sources']);
            $sortedActual = $sourceIds;
            sort($sortedActual);
            sort($expectedIds);
            if ($sortedActual !== $expectedIds) {
                throw ValidationException::withMessages(['workflow' => 'Upload one replacement for every selected artwork.']);
            }
        }
        $selectedArtwork = array_map('intval', $validated['revision_document_ids'] ?? []);
        if (in_array($validated['decision'], ['revise', 'cancel_artwork'], true)) {
            $permitted = array_map(static fn ($row): int => (int) $row['id'], $taskData['artwork_files'] ?? []);
            if ($selectedArtwork === [] || array_diff($selectedArtwork, $permitted) !== []) {
                throw ValidationException::withMessages(['workflow' => 'Select a current artwork file to review.']);
            }
        }
        $revisionItems = [];
        if ($validated['decision'] === 'revise') {
            foreach ($selectedArtwork as $docId) {
                $note = trim((string) ($validated['revision_notes'][$docId] ?? $validated['comment'] ?? ''));
                if ($note === '') {
                    throw ValidationException::withMessages(['workflow' => 'Describe the requested change for every selected artwork file.']);
                }
                $revisionItems[] = ['document_id' => $docId, 'comment' => $note];
            }
        }
        if ($isDocumentTask && ($validated['decision'] !== 'upload' || $files === [])) {
            throw ValidationException::withMessages(['workflow' => 'Upload at least one file to complete this task.']);
        }
        if (! $isDocumentTask && $validated['decision'] === 'upload') {
            throw ValidationException::withMessages(['workflow' => 'This task cannot be completed as an upload action.']);
        }
        $url = rtrim((string) config('flowtrack_bridge.buyer_url'), '/');
        $secret = (string) config('flowtrack_bridge.secret');
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        abort_unless(config('flowtrack_bridge.enabled') && strlen($secret) >= 32
            && is_string($host) && $host !== '' && ! str_contains($url, '@')
            && ! str_contains($url, '?') && ! str_contains($url, '#')
            && ($scheme === 'https' || ($scheme === 'http' && app()->environment(['local', 'testing'])
                && in_array($host, ['localhost', '127.0.0.1'], true))), 503);
        $event = (string) Str::uuid();
        $inputKeys = [
            'qty_received', 'qty_inspected', 'qty_accepted', 'qty_rejected',
            'issue_category', 'erp_reference', 'carrier', 'tracking_number',
            'client_name', 'contact_name', 'phone_country_code', 'phone_number',
            'address', 'city', 'state', 'country', 'postal_code',
        ];
        $payload = [];
        foreach ($inputKeys as $field) {
            if (isset($validated[$field]) && $validated[$field] !== '') $payload[$field] = $validated[$field];
        }
        if ($sourceIds !== []) $payload['file_source_ids'] = $sourceIds;
        if ($selectedArtwork !== []) {
            $payload['revision_document_ids'] = $selectedArtwork;
            $payload['cancel_artwork_document_ids'] = $selectedArtwork;
        }
        if ($revisionItems !== []) $payload['revision_items'] = $revisionItems;
        $body = json_encode([
            'event_id' => $event, 'request_uuid' => (string) Str::uuid(),
            'supplier_id' => (int) $company->flowtrack_supplier_id,
            'order_id' => (int) ($snapshot['source_id'] ?? 0),
            'task_id' => $task, 'user_id' => (int) $request->user()->id,
            'user_name' => (string) $request->user()->name,
            'expected_status' => (string) $taskData['status'],
            'decision' => $validated['decision'], 'comment' => (string) ($validated['comment'] ?? ''),
            'payload' => $payload,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $digests = array_map(static fn ($file): string => hash_file('sha256', $file->getRealPath()), $files);
        $digestHeader = implode(',', $digests);
        $time = (string) time();
        try {
            $signedContent = $time."\n".$event."\n".$body.($files !== [] ? "\n".$digestHeader : '');
            $client = Http::connectTimeout(3)->timeout($files !== [] ? 300 : 20)->withoutRedirecting()
                ->withHeaders([
                    'X-Supplier-Timestamp' => $time, 'X-Supplier-Event' => $event,
                    'X-Supplier-Signature' => hash_hmac('sha256', $signedContent, $secret),
                    'Accept' => 'application/json',
                ]);
            if ($files !== []) {
                $client = $client->withHeaders(['X-Supplier-Digests' => $digestHeader]);
                $handles = [];
                try {
                    foreach ($files as $i => $file) {
                        $handle = fopen($file->getRealPath(), 'rb');
                        if ($handle === false) throw new \RuntimeException('Unable to stream task attachment.');
                        $handles[] = $handle;
                        $client = $client->attach('files['.$i.']', $handle,
                            basename(str_replace('\\', '/', $file->getClientOriginalName())));
                    }
                    $response = $client->post($url.'/api/supplier-bridge/workflow-task-actions', ['data' => $body]);
                } finally {
                    foreach ($handles as $handle) fclose($handle);
                }
            } else {
                $response = $client->withBody($body, 'application/json')
                    ->post($url.'/api/supplier-bridge/workflow-task-actions');
            }
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['workflow' => 'FlowTracker is not reachable. No task change was confirmed.']);
        }
        if (! $response->successful() || $response->json('received') !== true) {
            // Log only the status and task/order IDs; never log signed payloads,
            // supplier secrets, uploaded documents, or other private data.
            Log::warning('FlowTracker rejected supplier workflow action', [
                'order_id' => (int) ($snapshot['source_id'] ?? 0),
                'task_id' => $task, 'http_status' => $response->status(),
            ]);
            throw ValidationException::withMessages(['workflow' => match ($response->status()) {
                403, 404 => 'This task is no longer assigned to your company.',
                409 => 'Task state changed in FlowTracker. Wait for synchronization and refresh.',
                413 => 'The file is too large for the configured server upload limit.',
                422 => 'FlowTracker rejected the task details or evidence. Check required fields and attachments.',
                default => 'FlowTracker did not accept the action. Please try again later.',
            }]);
        }
        // Audit only actions FlowTracker acknowledged. The supplier portal does not
        // persist comments, uploaded files, signed requests, or pricing in this feed.
        // Logging is best-effort: a local audit failure must not turn an already
        // accepted FlowTracker task into an apparent failed submission.
        try {
            DB::transaction(function () use ($company, $order, $request, $event, $task, $stageName, $taskData, $validated, $response): void {
                DB::table('supplier_order_activities')->insert([
                    'supplier_company_id' => $company->id,
                    'supplier_resource_id' => $order->id,
                    'supplier_user_id' => $request->user()->id,
                    'event_id' => $event,
                    'task_id' => $task,
                    'stage_name' => mb_substr($stageName, 0, 100),
                    'task_title' => mb_substr((string) ($taskData['title'] ?? 'Order task'), 0, 255),
                    'actor_name' => mb_substr((string) $request->user()->name, 0, 255),
                    'decision' => $validated['decision'],
                    'task_status' => mb_substr((string) ($response->json('task_status') ?: 'Accepted'), 0, 100),
                    'created_at' => now(),
                ]);
                $company->newQuery()->whereKey($company->id)->increment('dashboard_revision');
            });
        } catch (\Throwable $exception) {
            Log::error('Supplier order activity audit could not be saved after confirmed action', [
                'supplier_company_id' => (int) $company->id,
                'supplier_order_id' => (int) $order->id,
                'task_id' => $task,
            ]);
        }
        // Reflect the ACTUAL result returned by FlowTracker. An ERP upload
        // transitions into Waiting for Client, not directly to Completed.
        $savedStatus = strtolower(trim((string) $response->json('task_status', '')));
        $message = match (true) {
            $key === 'ART_CLIENT_ERP_DECISION' && $validated['decision'] === 'erp_uploaded'
                => 'Client ERP upload recorded in FlowTracker. The task is now waiting for the client decision; it is not completed yet.',
            $key === 'ART_CLIENT_ERP_DECISION' && $validated['decision'] === 'sample'
                => 'Client sample requirement recorded in FlowTracker. The Sample Approval task is the next step.',
            $key === 'ART_CLIENT_ERP_DECISION' && $validated['decision'] === 'revise'
                => 'Client revision request recorded in FlowTracker. The artwork revision workflow has restarted.',
            $savedStatus === 'completed' || $validated['decision'] === 'confirm'
                => 'Task action accepted and recorded in FlowTracker.',
            default => 'Task action accepted by FlowTracker.',
        };
        return redirect()->route('supplier.orders.show', $id)->with('success',
            $message.' Your order view will refresh when the confirmed update arrives.');
    }
}
