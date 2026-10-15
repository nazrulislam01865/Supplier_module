<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/** Authenticates the supplier's private dashboard channel without exposing Reverb secrets. */
final class SupplierDashboardRealtimeController extends Controller
{
    public function authorize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'socket_id' => ['required', 'regex:/^\d+\.\d+$/'],
            'channel_name' => ['required', 'string', 'max:150'],
        ]);
        $company = $request->user()->company;
        abort_unless($company && $company->active, 403);
        $supplierId = (int) $company->flowtrack_supplier_id;
        $channel = 'private-flowtrack.supplier.'.$supplierId.'.dashboard';
        abort_unless($supplierId > 0 && hash_equals($channel, $data['channel_name']), 403);

        $key = (string) config('supplier_realtime.key');
        $secret = (string) config('flowtrack_bridge.secret');
        $url = rtrim((string) config('flowtrack_bridge.buyer_url'), '/');
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        abort_unless($key !== '' && config('flowtrack_bridge.enabled') && strlen($secret) >= 32
            && is_string($host) && $host !== ''
            && ! str_contains($url, '@') && ! str_contains($url, '?') && ! str_contains($url, '#')
            && ($scheme === 'https' || ($scheme === 'http' && app()->environment(['local', 'testing'])
                && in_array($host, ['127.0.0.1', 'localhost'], true))), 503);

        $event = (string) Str::uuid();
        $body = json_encode([
            'event_id' => $event, 'supplier_id' => $supplierId, 'resource' => 'dashboard',
            'socket_id' => $data['socket_id'], 'channel_name' => $channel,
        ], JSON_THROW_ON_ERROR);
        $time = (string) time();
        try {
            $response = Http::connectTimeout(2)->timeout(4)->withHeaders([
                'X-Supplier-Event' => $event,
                'X-Supplier-Timestamp' => $time,
                'X-Supplier-Signature' => hash_hmac('sha256', $time."\n".$event."\n".$body, $secret),
                'Content-Type' => 'application/json', 'Accept' => 'application/json',
            ])->withBody($body, 'application/json')->post($url.'/api/supplier-bridge/realtime-auth');
        } catch (\Throwable) {
            abort(503, 'Realtime channel unavailable. Automatic refresh will continue.');
        }
        abort_unless($response->successful(), 503);
        $auth = $response->json('auth');
        abort_unless(is_string($auth) && preg_match('/^'.preg_quote($key, '/').':[0-9a-f]{64}$/', $auth), 503);
        return response()->json(['auth' => $auth])->header('Cache-Control', 'private, no-store');
    }
}
