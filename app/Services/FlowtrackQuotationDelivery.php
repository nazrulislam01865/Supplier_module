<?php

namespace App\Services;

use App\Models\SupplierQuotation;
use App\Models\SupplierDocument;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/** Durable, supplier-scoped reverse integration. Files are delivered BEFORE finalization. */
final class FlowtrackQuotationDelivery
{
    /** Fail clearly before delivery without exposing the shared signing secret. */
    public function assertConfigured(): void
    {
        $this->endpoint();
    }

    private function endpoint(): array
    {
        if (!config('flowtrack_bridge.enabled')) {
            throw new RuntimeException('Supplier quotation bridge is disabled. Set FLOWTRACK_BRIDGE_ENABLED=true in the Supplier Portal.');
        }
        $secret = (string) config('flowtrack_bridge.secret');
        if (strlen($secret) < 32) {
            throw new RuntimeException('Supplier quotation bridge secret is missing or too short. Configure the same shared secret in both applications.');
        }
        $url = rtrim(trim((string) config('flowtrack_bridge.buyer_url')), '/');
        if ($url === '') {
            throw new RuntimeException('FLOWTRACK_BUYER_URL is missing in the Supplier Portal .env (local example: http://127.0.0.1:8000).');
        }
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '' || str_contains($url, '@') || str_contains($url, '?') || str_contains($url, '#') ||
            !($scheme === 'https' || ($scheme === 'http' && app()->environment(['local', 'testing']) && in_array($host, ['127.0.0.1', 'localhost'], true)))) {
            throw new RuntimeException('FLOWTRACK_BUYER_URL must be HTTPS, or HTTP on localhost in a local/test environment.');
        }
        return [$url, $secret];
    }

    private function rejection(string $operation, $response): string
    {
        $message = (string) ($response->json('message') ?: 'Buyer did not confirm receipt');
        $errors = $response->json('errors');
        if (is_array($errors) && $errors !== []) {
            $field = array_key_first($errors);
            $detail = $errors[$field];
            $message .= ' ['.$field.': '.(is_array($detail) ? (string) ($detail[0] ?? 'Invalid') : (string) $detail).']';
        }
        return $operation.' returned HTTP '.$response->status().': '.$message;
    }

    public function send(int $id, bool $retryNow = false): bool
    {
        $claimed = SupplierQuotation::query()->whereKey($id)->where('status','submitted')
            ->where(fn ($q) => $q->where(fn ($pending) => $pending->where('sync_status','pending')
                ->when(!$retryNow, fn ($eligible) => $eligible->where(fn ($time) => $time->whereNull('sync_next_attempt_at')->orWhere('sync_next_attempt_at','<=',now()))))
                ->orWhere(fn ($stale) => $stale->where('sync_status','sending')->where('sync_locked_at','<',now()->subMinutes(30))))
            ->update(['sync_status'=>'sending','sync_locked_at'=>now()]);
        if (!$claimed) return false;
        try {
            $quote = SupplierQuotation::query()->with(['resource.company'])->findOrFail($id);
            $resource = $quote->resource;
            if (!$resource || $resource->type !== 'rfq' || !$resource->company?->active) {
                throw new RuntimeException('RFQ or supplier is no longer active.');
            }
            $supplierId = (int) $resource->company->flowtrack_supplier_id;
            $invitationId = (int) ($resource->payload['invitation_id'] ?? 0);
            if ($supplierId < 1 || $invitationId < 1 || !$quote->submission_uuid) {
                throw new RuntimeException('RFQ supplier mapping is incomplete.');
            }
            [$url,$secret] = $this->endpoint();
            $documents = SupplierDocument::query()->where('supplier_company_id',$quote->supplier_company_id)
                ->where('supplier_resource_id',$resource->id)->where('scan_status','clean')
                ->orderBy('id')->limit(11)->get();
            if ($documents->count() > 10) throw new RuntimeException('Maximum ten quotation documents are supported.');
            $manifest = [];
            foreach ($documents as $document) {
                $path = Storage::disk('private')->path($document->storage_path);
                if (!is_file($path) || !is_readable($path) || filesize($path) > 20971520) {
                    throw new RuntimeException('A quotation attachment is missing or too large.');
                }
                $digest = hash_file('sha256', $path);
                $time = (string) time();
                $signature = hash_hmac('sha256', implode("\n", [$time,$supplierId,$invitationId,$document->id,$digest]), $secret);
                $stream = fopen($path, 'rb');
                if (!$stream) throw new RuntimeException('Unable to read quotation attachment.');
                try {
                    $response = Http::connectTimeout(3)->timeout(90)->withHeaders([
                        'X-Supplier-Timestamp' => $time,
                        'X-Supplier-Id' => (string) $supplierId,
                        'X-Supplier-Invitation' => (string) $invitationId,
                        'X-Supplier-Document' => (string) $document->id,
                        'X-Supplier-Digest' => $digest,
                        'X-Supplier-Signature' => $signature,
                        'Accept' => 'application/json',
                    ])->withoutRedirecting()->attach('file', $stream, basename($document->original_name))
                        ->post($url.'/api/supplier-bridge/rfq-quotation-files');
                } finally { fclose($stream); }
                if (!$response->successful() || $response->json('received') !== true) {
                    throw new RuntimeException($this->rejection('Quotation document delivery', $response));
                } 
                $manifest[] = [
                    'id' => (int) $document->id,
                    'name' => basename($document->original_name),
                    'document_type' => $document->document_type,
                    'size' => (int) $document->size,
                    'sha256' => $digest,
                ];
            }
            $event = (string) Str::uuid();
            $body = json_encode([
                'event_id' => $event,
                'supplier_id' => $supplierId,
                'invitation_id' => $invitationId,
                'submission_uuid' => $quote->submission_uuid,
                'total_minor_units' => (int) $quote->total_minor_units,
                'quotation' => $quote->data,
                'documents' => $manifest,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $time = (string) time();
            $response = Http::connectTimeout(3)->timeout(30)->withHeaders([
                'X-Supplier-Timestamp' => $time,
                'X-Supplier-Event' => $event,
                'X-Supplier-Signature' => hash_hmac('sha256', $time."\n".$event."\n".$body, $secret),
                'Accept' => 'application/json',
            ])->withoutRedirecting()->withBody($body,'application/json')->post($url.'/api/supplier-bridge/rfq-quotations');
            if (!$response->successful() || $response->json('received') !== true) {
                throw new RuntimeException($this->rejection('Quotation submission', $response));
            }
            SupplierQuotation::query()->whereKey($id)->update([
                'sync_status'=>'delivered','sync_error'=>null,'sync_next_attempt_at'=>null,'sync_locked_at'=>null,'synced_at'=>now(),
            ]);
            return true;
        } catch (Throwable $exception) {
            $attempts = (int) SupplierQuotation::query()->whereKey($id)->value('sync_attempts') + 1;
            SupplierQuotation::query()->whereKey($id)->update([
                'sync_status'=>'pending','sync_attempts'=>$attempts,'sync_locked_at'=>null,
                'sync_next_attempt_at'=>now()->addSeconds(min(3600, 30 * (2 ** min($attempts,7)))),
                'sync_error'=>mb_substr($exception->getMessage(),0,255),
            ]);
            report($exception);
            return false;
        }
    }
}
