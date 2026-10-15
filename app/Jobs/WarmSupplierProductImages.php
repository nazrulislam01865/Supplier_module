<?php
namespace App\Jobs;

use App\Models\SupplierResource;
use App\Services\SupplierImageCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Walk only this company's products after accepting a catalog; binary transfers are separate jobs. */
final class WarmSupplierProductImages implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public function __construct(public readonly int $companyId) {}
    public function handle(SupplierImageCache $cache): void
    {
        SupplierResource::query()->where('supplier_company_id', $this->companyId)
            ->where('type', 'product')->select(['id', 'supplier_company_id', 'payload'])
            ->chunkById(200, function ($resources) use ($cache): void {
                foreach ($resources as $resource) {
                    $p = $resource->payload ?? [];
                    $version = (string) ($p['image_version'] ?? '');
                    $ext = (string) ($p['image_extension'] ?? '');
                    if ($cache->path($this->companyId, (int) $resource->id, $version, $ext)
                        && ! $cache->exists($this->companyId, (int) $resource->id, $version, $ext)) {
                        SyncSupplierProductImage::dispatch($this->companyId, (int) $resource->id)->onQueue('default');
                    }
                }
            });
    }
}
