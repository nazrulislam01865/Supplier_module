<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/** Reuse the configured, server-to-server supplier bridge; browsers never hold the secret. */
final class FlowtrackPurchaseOrderDelivery
{
    public function endpoint(): array
    {
        $url = rtrim((string) config('flowtrack_bridge.buyer_url'), '/');
        $secret = (string) config('flowtrack_bridge.secret');
        $host = parse_url($url, PHP_URL_HOST);
        $scheme = parse_url($url, PHP_URL_SCHEME);
        if (! config('flowtrack_bridge.enabled') || strlen($secret) < 32 || ! is_string($host) || $host === ''
            || str_contains($url, '@') || str_contains($url, '?') || str_contains($url, '#')
            || ! ($scheme === 'https' || ($scheme === 'http' && app()->environment(['local', 'testing'])
                && in_array($host, ['localhost', '127.0.0.1'], true)))) {
            throw new RuntimeException('The secure FlowTracker integration is not configured.');
        }
        return [$url, $secret];
    }

    /** @return array{status:int,data:array} */
    public function save(array $payload): array
    {
        [$url, $secret] = $this->endpoint();
        $event = (string) Str::uuid();
        $payload['event_id'] = $event;
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $time = (string) time();
        $response = Http::connectTimeout(3)->timeout(20)->withoutRedirecting()->withHeaders([
            'X-Supplier-Timestamp' => $time,
            'X-Supplier-Event' => $event,
            'X-Supplier-Signature' => hash_hmac('sha256', $time."\n".$event."\n".$body, $secret),
            'Accept' => 'application/json',
        ])->withBody($body, 'application/json')->post($url.'/api/supplier-bridge/purchase-orders');
        $data = $response->json();
        return ['status' => $response->status(), 'data' => is_array($data) ? $data : []];
    }
}
