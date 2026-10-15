<?php

namespace App\Http\Controllers;

use App\Services\SupplierScope;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;

/** Authenticated browser -> supplier server -> signed FlowTracker file. */
final class SupplierOrderArtworkController extends Controller
{
    public function __invoke(Request $request, int $id, int $document, SupplierScope $scope)
    {
        $order = $scope->resource($request, $id, 'order');
        abort_if($order->status === 'revoked', 404);
        $data = $order->payload ?? [];
        $manifest = collect($data['documents'] ?? [])->first(
            static fn ($file) => (int) ($file['id'] ?? 0) === $document,
        );
        abort_unless($manifest && (int) ($data['source_id'] ?? 0) > 0, 404);
        $url = (string) config('flowtrack_bridge.buyer_url');
        $secret = (string) config('flowtrack_bridge.secret');
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        abort_unless(config('flowtrack_bridge.enabled') && strlen($secret) >= 32
            && is_string($host) && $host !== '' && ! str_contains($url, '@')
            && ! str_contains($url, '?') && ! str_contains($url, '#')
            && ($scheme === 'https' || ($scheme === 'http' && app()->environment(['local', 'testing'])
                && in_array($host, ['127.0.0.1', 'localhost'], true))), 503);
        $supplier = (int) $request->user()->company->flowtrack_supplier_id;
        $source = (int) $data['source_id'];
        abort_if($supplier < 1, 404);
        $time = (string) time();
        $response = Http::connectTimeout(4)->timeout(600)->withHeaders([
            'X-FlowTrack-Timestamp' => $time,
            'X-FlowTrack-Supplier' => (string) $supplier,
            'X-FlowTrack-Signature' => hash_hmac('sha256', $time."\n".$supplier."\n".$source."\n".$document, $secret),
            'Accept' => 'application/octet-stream',
        ])->withOptions(['stream' => true])->withoutRedirecting()
            ->get($url.'/api/supplier-bridge/order-artwork/'.$source.'/'.$document);
        abort_unless($response->successful(), 404);
        $expected = (int) $manifest['size'];
        abort_unless($expected > 0 && $expected <= 419430400, 404);
        // Use a bounded, disk-backed stream. Artwork uploads can be 400 MB;
        // never buffer the whole response in PHP memory on the portal server.
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
        $name = basename(str_replace('\\', '/', (string) $manifest['name'])) ?: 'approved-artwork';
        return response()->streamDownload(static function () use ($temporary): void {
            try { fpassthru($temporary); } finally { fclose($temporary); }
        }, $name, [
            'Content-Type' => 'application/octet-stream',
            'Content-Length' => (string) $expected,
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
