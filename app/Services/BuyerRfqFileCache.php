<?php

namespace App\Services;

use App\Models\SupplierResource;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/** Private, supplier-scoped cache of signed buyer reference documents. */
final class BuyerRfqFileCache
{
    public function path(SupplierResource $rfq, array $document): string
    {
        $identity = implode('|', [
            (int) ($rfq->payload['invitation_id'] ?? 0),
            (int) $document['id'],
            (int) ($document['size'] ?? 0),
            (string) ($document['version'] ?? ''),
        ]);
        return 'buyer-rfq/'.(int) $rfq->supplier_company_id.'/'.(int) $rfq->id.'/'
            .(int) $document['id'].'-'.hash('sha256', $identity).'.bin';
    }

    public function cached(SupplierResource $rfq, array $document): ?string
    {
        $path = $this->path($rfq, $document);
        $disk = Storage::disk('private');
        return (int) ($document['size'] ?? 0) > 0 && $disk->exists($path) && $disk->size($path) === (int) ($document['size'] ?? 0)
            ? $path : null;
    }

    public function fetch(SupplierResource $rfq, array $document, bool $supplierTask = false): string
    {
        $url = (string) config('flowtrack_bridge.buyer_url');
        $secret = (string) config('flowtrack_bridge.secret');
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        if (! config('flowtrack_bridge.enabled') || strlen($secret) < 32 || ! is_string($host) || $host === ''
            || str_contains($url, '@') || str_contains($url, '?') || str_contains($url, '#')
            || ! ($scheme === 'https' || ($scheme === 'http' && app()->environment(['local', 'testing'])
                && in_array($host, ['localhost', '127.0.0.1'], true)))) {
            throw new RuntimeException('Buyer RFQ document bridge is not configured.');
        }
        $supplier = (int) $rfq->company->flowtrack_supplier_id;
        $invitation = (int) ($rfq->payload['invitation_id'] ?? 0);
        $documentId = (int) $document['id'];
        if ($supplier < 1 || $invitation < 1 || $documentId < 1) {
            throw new RuntimeException('Invalid buyer RFQ document scope.');
        }
        $time = (string) time();
        $version = (string) ($document['version'] ?? '');
        $signedMessage = $time."\n".$supplier."\n".$invitation."\n".$documentId;
        if ($version !== '') $signedMessage .= "\n".$version;
        $response = Http::connectTimeout(2)->timeout(20)->withHeaders([
            'X-FlowTrack-Timestamp' => $time,
            'X-FlowTrack-Supplier' => (string) $supplier,
            'X-FlowTrack-Signature' => hash_hmac('sha256', $signedMessage, $secret),
            'X-FlowTrack-Document-Version' => $version,
            'Accept' => 'application/octet-stream',
        ])->withoutRedirecting()->get($url.'/api/supplier-bridge/'.($supplierTask ? 'rfq-task-documents' : 'rfq-documents').'/'.$invitation.'/'.$documentId);
        if (! $response->successful() || ((int) ($document['size'] ?? 0) > 0 && strlen($response->body()) !== (int) $document['size'])
            || strlen($response->body()) > 20971520) {
            throw new RuntimeException('Buyer RFQ document was unavailable or changed.');
        }
        return $response->body();
    }

    public function warm(SupplierResource $rfq, array $document): void
    {
        if ($this->cached($rfq, $document)) return;
        $bytes = $this->fetch($rfq, $document);
        Storage::disk('private')->put($this->path($rfq, $document), $bytes);
    }
}
