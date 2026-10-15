<?php
namespace App\Jobs;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Services\SupplierImageCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/** Transfers an actual image once per version, not on every list/detail page view. */
final class SyncSupplierProductImage implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 4;
    public function backoff(): array { return [30, 120, 300, 900]; }
    public function __construct(public readonly int $companyId, public readonly int $resourceId) {}

    public function handle(SupplierImageCache $cache): void
    {
        $resource = SupplierResource::query()->where('supplier_company_id', $this->companyId)
            ->where('type', 'product')->find($this->resourceId);
        if (! $resource) return;
        $company = SupplierCompany::query()->whereKey($this->companyId)->where('active', true)->first();
        if (! $company || ! $company->flowtrack_supplier_id) return;
        $payload = $resource->payload ?? [];
        $version = (string) ($payload['image_version'] ?? '');
        $extension = (string) ($payload['image_extension'] ?? '');
        $path = $cache->path($this->companyId, $this->resourceId, $version, $extension);
        if (! $path || $cache->exists($this->companyId, $this->resourceId, $version, $extension)) return;
        if (! preg_match('/^FT-P-([1-9][0-9]*)$/', $resource->external_reference, $parts)) return;
        $url = rtrim((string) config('flowtrack_bridge.buyer_url'), '/');
        $secret = (string) config('flowtrack_bridge.secret');
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! config('flowtrack_bridge.enabled') || strlen($secret) < 32 || ! is_string($host)
            || $host === '' || str_contains($url, '@') || str_contains($url, '?') || str_contains($url, '#')
            || ! ($scheme === 'https' || (app()->environment(['local','testing'])
                && $scheme === 'http' && in_array($host, ['127.0.0.1','localhost'], true)))) {
            throw new \RuntimeException('Image bridge is not securely configured.');
        }
        $supplier = (int) $company->flowtrack_supplier_id;
        $product = (int) $parts[1];
        $time = (string) time();
        $response = Http::connectTimeout(2)->timeout(10)->withoutRedirecting()->withHeaders([
            'X-Supplier-Asset-Time' => $time,
            'X-Supplier-Asset-Signature' => hash_hmac('sha256', $supplier."\n".$product."\n".$time, $secret),
            'X-Supplier-Image-Version' => $version,
            'Accept' => 'image/png, image/jpeg, image/webp',
        ])->get($url.'/api/supplier-bridge/product-image/'.$supplier.'/'.$product);
        if (! $response->successful()) throw new \RuntimeException('Source image download failed (HTTP '.$response->status().').');
        $bytes = $response->body();
        if (strlen($bytes) < 10 || strlen($bytes) > 5 * 1024 * 1024) {
            throw new \RuntimeException('Source image size is invalid.');
        }
        $detected = @getimagesizefromstring($bytes);
        $allowed = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'];
        if (($detected['mime'] ?? null) !== $allowed[strtolower($extension)]) {
            throw new \RuntimeException('Source image format is invalid.');
        }
        // Never replace a newer version if a product photo was edited while downloading.
        $current = SupplierResource::query()->where('supplier_company_id', $this->companyId)
            ->whereKey($this->resourceId)->value('payload');
        $current = is_array($current) ? $current : json_decode((string) $current, true);
        if (($current['image_version'] ?? '') !== $version) return;
        Storage::disk('private')->put($path, $bytes);
        // Keep only current versions for this resource; avoids unbounded local disk usage.
        foreach (Storage::disk('private')->files('supplier-product-images/'.$this->companyId.'/'.$this->resourceId) as $old) {
            if ($old !== $path) Storage::disk('private')->delete($old);
        }
    }
}
