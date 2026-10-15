<?php
namespace App\Console\Commands;

use App\Jobs\SyncSupplierProductImage;
use App\Models\SupplierResource;
use App\Services\SupplierImageCache;
use Illuminate\Console\Command;

final class RetrySupplierProductImages extends Command
{
    protected $signature = 'supplier:retry-product-images {--limit=100}';
    protected $description = 'Queue missing or changed supplier product images (bounded fallback)';

    public function handle(SupplierImageCache $cache): int
    {
        $limit = min(500, max(1, (int) $this->option('limit')));
        $queued = 0;
        // A bounded scan; only resource rows are examined, never product media in the database.
        SupplierResource::query()->where('type', 'product')->whereIn('status', ['active','inactive'])
            ->select(['id', 'supplier_company_id', 'payload'])
            ->chunkById(200, function ($items) use (&$queued, $limit, $cache): bool {
                foreach ($items as $item) {
                    $version = (string) ($item->payload['image_version'] ?? '');
                    $ext = (string) ($item->payload['image_extension'] ?? '');
                    if ($cache->path((int) $item->supplier_company_id, (int) $item->id, $version, $ext)
                        && ! $cache->exists((int) $item->supplier_company_id, (int) $item->id, $version, $ext)) {
                        SyncSupplierProductImage::dispatch((int) $item->supplier_company_id, (int) $item->id)
                            ->onQueue('default');
                        if (++$queued >= $limit) return false;
                    }
                }
                return true;
            });
        $this->info('Missing product images queued: '.$queued);
        return self::SUCCESS;
    }
}
