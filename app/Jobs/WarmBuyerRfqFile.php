<?php

namespace App\Jobs;

use App\Models\SupplierResource;
use App\Services\BuyerRfqFileCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Dedicated file queue: large downloads must not block critical RFQ delivery. */
final class WarmBuyerRfqFile implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 30;
    public array $backoff = [2, 10, 30];

    public function __construct(public readonly int $resourceId, public readonly int $documentId, public readonly string $version) {}

    public function handle(BuyerRfqFileCache $cache): void
    {
        $rfq = SupplierResource::query()->with('company:id,flowtrack_supplier_id')
            ->where('type', 'rfq')->where('status', '!=', 'revoked')->find($this->resourceId);
        if (! $rfq) return;
        $document = collect($rfq->payload['documents'] ?? [])->first(
            fn (array $doc): bool => (int) $doc['id'] === $this->documentId
                && (string) ($doc['version'] ?? '') === $this->version,
        );
        if (! $document) return; // A newer RFQ projection replaced the document.
        $cache->warm($rfq, $document);
    }
}
