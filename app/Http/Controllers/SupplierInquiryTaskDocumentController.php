<?php

namespace App\Http\Controllers;

use App\Services\BuyerRfqFileCache;
use App\Services\SupplierScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Symfony\Component\HttpFoundation\HeaderUtils;

/** Logged-in supplier can retrieve only its own task receipt via the signed FlowTracker bridge. */
final class SupplierInquiryTaskDocumentController extends Controller
{
    public function __invoke(Request $request, int $id, int $document, SupplierScope $scope, BuyerRfqFileCache $bridge)
    {
        $rfq = $scope->resource($request, $id, 'rfq');
        abort_if($rfq->status === 'revoked', 404);
        $task = $rfq->payload['supplier_task'] ?? null;
        $manifest = collect($task['uploaded_files'] ?? [])->first(
            static fn ($file): bool => is_array($file) && (int) ($file['id'] ?? 0) === $document,
        );
        abort_unless($manifest && (int) ($rfq->payload['invitation_id'] ?? 0) > 0, 404);
        abort_unless((int) ($manifest['size'] ?? 0) > 0 && (int) $manifest['size'] <= 20971520, 404);

        try {
            // No eager prefetch or extra page-load query: fetch only when requested.
            $bytes = $bridge->fetch($rfq->loadMissing('company'), $manifest, true);
        } catch (\Throwable) {
            abort(404, 'Supplier task file is unavailable. Refresh the RFQ and try again.');
        }
        $mime = strtolower((string) ($manifest['mime_type'] ?? ''));
        $inlineTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        $inline = $request->boolean('view') && in_array($mime, $inlineTypes, true);
        $name = basename(str_replace('\\', '/', (string) $manifest['name'])) ?: 'costing-file';

        return response($bytes, 200, [
            'Content-Type' => $inline ? $mime : 'application/octet-stream',
            'Content-Disposition' => HeaderUtils::makeDisposition(
                $inline ? HeaderUtils::DISPOSITION_INLINE : HeaderUtils::DISPOSITION_ATTACHMENT, $name,
            ),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
