<?php
namespace App\Services;

use Illuminate\Support\Facades\Storage;

/** Image paths are keyed by company, product-resource and immutable source fingerprint. */
final class SupplierImageCache
{
    public function path(int $companyId, int $resourceId, string $version, string $extension): ?string
    {
        if ($companyId < 1 || $resourceId < 1 || ! preg_match('/^[a-f0-9]{64}$/', $version)
            || ! in_array(strtolower($extension), ['png', 'jpg', 'jpeg', 'webp'], true)) return null;
        return 'supplier-product-images/'.$companyId.'/'.$resourceId.'/'.$version.'.'.strtolower($extension);
    }

    public function exists(int $companyId, int $resourceId, string $version, string $extension): bool
    {
        $path = $this->path($companyId, $resourceId, $version, $extension);
        return $path !== null && Storage::disk('private')->exists($path);
    }
}
