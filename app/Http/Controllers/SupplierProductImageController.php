<?php
namespace App\Http\Controllers;

use App\Services\SupplierImageCache;
use App\Services\SupplierScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;

final class SupplierProductImageController extends Controller
{
    public function __invoke(Request $request, int $id, SupplierScope $scope, SupplierImageCache $cache)
    {
        $product = $scope->resource($request, $id, 'product');
        $payload = $product->payload ?? [];
        $path = $cache->path((int) $product->supplier_company_id, (int) $product->id,
            (string) ($payload['image_version'] ?? ''), (string) ($payload['image_extension'] ?? ''));
        abort_unless($path && Storage::disk('private')->exists($path), 404);
        return Storage::disk('private')->response($path, null, [
            'Cache-Control' => 'private, max-age=300', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
