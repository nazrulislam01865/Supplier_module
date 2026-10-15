<?php

namespace App\Http\Controllers;

use App\Services\BuyerRfqFileCache;
use App\Services\SupplierScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** Browser authorization is supplier-scoped; cached bytes remain private. */
final class BuyerRfqDocumentController extends Controller
{
    public function __invoke(Request $request, int $id, int $document, SupplierScope $scope, BuyerRfqFileCache $cache)
    {
        $rfq = $scope->resource($request, $id, 'rfq');
        abort_if($rfq->status === 'revoked', 404);
        $manifest = collect($rfq->payload['documents'] ?? [])->first(
            fn (array $doc): bool => (int) $doc['id'] === $document,
        );
        abort_unless($manifest && (int) ($rfq->payload['invitation_id'] ?? 0) > 0, 404);
        abort_if((int) ($manifest['size'] ?? 0) > 20971520, 413);

        $cachedPath = $cache->cached($rfq, $manifest);
        try {
            // A newly created RFQ can be opened before the prefetch worker finishes.
            $data = $cachedPath ? Storage::disk('private')->get($cachedPath) : $cache->fetch($rfq->loadMissing('company'), $manifest);
        } catch (\Throwable) {
            abort(404, 'RFQ reference document is not available yet.');
        }
        return response($data, 200, [
            'Content-Type' => 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, basename($manifest['name'])),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
