<?php

namespace App\Http\Controllers;

use App\Services\SupplierScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

/** Authenticated supplier-scoped QC evidence download via the signed buyer bridge. */
final class SupplierOrderQcDocumentController extends Controller
{
    public function __invoke(Request $request, int $id, int $document, SupplierScope $scope)
    {
        $order = $scope->resource($request, $id, 'order');
        abort_if($order->status === 'revoked', 404);
        $company = $request->user()->company;
        abort_unless($company && $company->active && (int) $company->flowtrack_supplier_id > 0, 404);
        $data = $order->payload ?? [];
        $manifest = null;
        foreach ($data['workflow']['stages'] ?? [] as $stage) {
            foreach ($stage['tasks'] ?? [] as $task) {
                if (! ($task['assigned_to_supplier'] ?? false)
                    || ! in_array((string) ($task['document_action_key'] ?? ''), ['QC_CHECK', 'QC_ISSUE'], true)) {
                    continue;
                }
                foreach ($task['document_files'] ?? [] as $file) {
                    if ((int) ($file['id'] ?? 0) === $document) {
                        $manifest = $file;
                        break 3;
                    }
                }
            }
        }
        $expected = (int) ($manifest['size'] ?? 0);
        abort_unless($manifest && $expected > 0 && $expected <= 20971520 && (int) ($data['source_id'] ?? 0) > 0, 404);
        $url = rtrim((string) config('flowtrack_bridge.buyer_url'), '/');
        $secret = (string) config('flowtrack_bridge.secret');
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        abort_unless(config('flowtrack_bridge.enabled') && strlen($secret) >= 32
            && is_string($host) && $host !== '' && ! str_contains($url, '@')
            && ! str_contains($url, '?') && ! str_contains($url, '#')
            && ($scheme === 'https' || ($scheme === 'http' && app()->environment(['local', 'testing'])
                && in_array($host, ['127.0.0.1', 'localhost'], true))), 503);
        $supplier = (int) $company->flowtrack_supplier_id;
        $source = (int) $data['source_id'];
        $time = (string) time();
        $response = Http::connectTimeout(4)->timeout(90)->withHeaders([
            'X-FlowTrack-Timestamp' => $time,
            'X-FlowTrack-Supplier' => (string) $supplier,
            'X-FlowTrack-Signature' => hash_hmac('sha256', $time."\n".$supplier."\n".$source."\n".$document, $secret),
            'Accept' => 'application/octet-stream',
        ])->withOptions(['stream' => true])->withoutRedirecting()
            ->get($url.'/api/supplier-bridge/order-qc-documents/'.$source.'/'.$document);
        abort_unless($response->successful(), 404);
        // Stream through bounded temporary storage; never buffer QC evidence in RAM.
        $temporary = tmpfile();
        abort_unless(is_resource($temporary), 503);
        try {
            $body = $response->toPsrResponse()->getBody();
            $received = 0;
            while (! $body->eof()) {
                $chunk = $body->read(65536);
                if ($chunk === '') break;
                $received += strlen($chunk);
                abort_if($received > $expected, 502);
                abort_unless(fwrite($temporary, $chunk) === strlen($chunk), 502);
            }
            abort_unless($received === $expected, 502);
            rewind($temporary);
        } catch (\Throwable $exception) {
            fclose($temporary);
            throw $exception;
        }
        $name = basename(str_replace('\\', '/', (string) $manifest['name'])) ?: 'qc-document';
        return response()->streamDownload(static function () use ($temporary): void {
            try { fpassthru($temporary); } finally { fclose($temporary); }
        }, $name, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => (string) $expected,
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
