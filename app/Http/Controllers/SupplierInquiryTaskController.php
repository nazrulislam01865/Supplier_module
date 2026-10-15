<?php

namespace App\Http\Controllers;

use App\Models\SupplierResource;
use App\Services\SupplierScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Browser upload -> signed direct FlowTracker write -> acknowledged local projection. */
final class SupplierInquiryTaskController extends Controller
{
    public function __invoke(Request $request, int $id, SupplierScope $scope)
    {
        $rfq = $scope->resource($request, $id, 'rfq');
        abort_if($rfq->status === 'revoked', 404);
        $company = $request->user()->company;
        abort_unless($company && $company->active && $company->flowtrack_supplier_id, 403);
        $data = $request->validate([
            'action' => ['required', 'in:upload,complete'],
            'expected_status' => ['required', 'string', 'max:100'],
            'file' => ['required_if:action,upload', 'nullable', 'file', 'max:20480',
                'mimes:pdf,doc,docx,xls,xlsx,csv,txt,jpg,jpeg,png,webp,gif,zip'],
        ]);
        $task = $rfq->payload['supplier_task'] ?? null;
        abort_unless(is_array($task) && (int) ($task['id'] ?? 0) > 0, 404);
        // Allow a completed legacy task to recover its missing upload. A real
        // FlowTracker document ID always locks the completed task, as before.
        $savedFiles = is_array($task['uploaded_files'] ?? null) ? $task['uploaded_files'] : [];
        $verifiedFiles = array_filter($savedFiles, static fn ($entry): bool =>
            is_array($entry) && (int) ($entry['id'] ?? 0) > 0);
        abort_if(($task['completed'] ?? false) &&
            ($data['action'] !== 'upload' || count($verifiedFiles) > 0),
            409, 'This task is already completed.');
        abort_unless(hash_equals((string) ($task['status'] ?? ''), $data['expected_status']), 409, 'Refresh the RFQ to see the latest task status.');
        abort_unless(in_array($rfq->status, ['open', 'draft', 'submitted'], true), 409);
        $secret = (string) config('flowtrack_bridge.secret');
        $url = (string) config('flowtrack_bridge.buyer_url');
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        abort_unless(config('flowtrack_bridge.enabled') && strlen($secret) >= 32 && is_string($host) && $host !== ''
            && ! str_contains($url, '@') && ! str_contains($url, '?') && ! str_contains($url, '#')
            && ($scheme === 'https' || ($scheme === 'http' && app()->environment(['local', 'testing'])
                && in_array($host, ['localhost', '127.0.0.1'], true))), 503);
        $event = (string) Str::uuid();
        $body = json_encode([
            'event_id' => $event, 'request_uuid' => (string) Str::uuid(),
            'supplier_id' => (int) $company->flowtrack_supplier_id,
            'invitation_id' => (int) ($rfq->payload['invitation_id'] ?? 0),
            'task_id' => (int) $task['id'],
            'action' => $data['action'], 'expected_status' => $data['expected_status'],
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $file = $data['action'] === 'upload' ? $request->file('file') : null;
        $digest = $file ? hash_file('sha256', $file->getRealPath()) : null;
        $time = (string) time();
        $signed = $time."\n".$event."\n".$body.($digest ? "\n".$digest : '');
        $client = Http::connectTimeout(3)->timeout($file ? 180 : 15)->withoutRedirecting()->withHeaders([
            'X-Supplier-Timestamp' => $time, 'X-Supplier-Event' => $event,
            'X-Supplier-Signature' => hash_hmac('sha256', $signed, $secret),
            'Accept' => 'application/json',
        ]);
        try {
            if ($file) {
                $handle = fopen($file->getRealPath(), 'rb');
                if ($handle === false) throw new \RuntimeException('Cannot read file.');
                try {
                    $response = $client->withHeaders(['X-Supplier-Digests' => $digest])
                        ->attach('file', $handle, basename($file->getClientOriginalName()))
                        ->post($url.'/api/supplier-bridge/inquiry-task-actions', ['data' => $body]);
                } finally { fclose($handle); }
            } else {
                $response = $client->withBody($body, 'application/json')
                    ->post($url.'/api/supplier-bridge/inquiry-task-actions');
            }
        } catch (\Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['task' => 'FlowTracker could not confirm this action. Refresh and try again.']);
        }
        if (! $response->successful() || $response->json('received') !== true) {
            throw ValidationException::withMessages(['task' => match ($response->status()) {
                403, 404 => 'This inquiry task is not available to your supplier company.',
                409 => 'FlowTracker already has this file or the task changed. Refresh the RFQ before retrying.',
                413 => 'The selected file exceeds the server upload limit.',
                422 => 'The required costing file or task details were not accepted.',
                429 => 'FlowTracker temporarily limited this task request. Wait a moment and retry once.',
                default => 'FlowTracker could not accept the action. Please retry.',
            }]);
        }
        // Do not claim an upload succeeded unless FlowTracker returned the
        // persisted document ID needed for scoped View/Download links.
        if ($file) {
            $receipt = $response->json('file');
            if (! is_array($receipt) || (int) ($receipt['id'] ?? 0) < 1
                || trim((string) ($receipt['name'] ?? '')) === ''
                || trim((string) ($receipt['mime_type'] ?? '')) === ''
                || (int) ($receipt['size'] ?? 0) < 1) {
                throw ValidationException::withMessages(['task' => 'FlowTracker did not confirm a document ID. Refresh the RFQ before retrying.']);
            }
        }
        // Never overwrite a newer RFQ snapshot that arrived while the signed
        // upload was in flight. Lock and merge only the acknowledged task data.
        $payload = DB::transaction(function () use ($rfq, $company, $response, $file): array {
            $current = SupplierResource::query()->whereKey($rfq->id)
                ->where('supplier_company_id', $company->id)->lockForUpdate()->firstOrFail();
            $payload = $current->payload;
            $payload['supplier_task']['status'] = (string) $response->json('status');
            $payload['supplier_task']['completed'] = (bool) $response->json('completed');
            if ($file) {
                $receipt = $response->json('file');
                if (is_array($receipt) && (int) ($receipt['id'] ?? 0) > 0) {
                    $receipts = is_array($payload['supplier_task']['uploaded_files'] ?? null)
                        ? $payload['supplier_task']['uploaded_files'] : [];
                    // Legacy filename-only entries are not downloadable and
                    // must not masquerade as a confirmed FlowTracker upload.
                    $receipts = array_values(array_filter($receipts, static fn ($existing): bool =>
                        is_array($existing) && (int) ($existing['id'] ?? 0) > 0
                        && (int) $existing['id'] !== (int) $receipt['id']));
                    $receipts[] = $receipt;
                    $payload['supplier_task']['uploaded_files'] = array_slice($receipts, -10);
                }
            }
            $current->payload = $payload;
            $current->save();
            $company->increment('dashboard_revision');
            return $payload;
        });
        return response()->json([
            'message' => $file ? 'File received by FlowTracker.' : 'Task completed in FlowTracker.',
            'task' => $payload['supplier_task'],
        ]);
    }
}
