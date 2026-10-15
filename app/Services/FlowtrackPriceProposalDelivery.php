<?php

namespace App\Services;

use App\Models\SupplierPriceProposal;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/** Durable, signed supplier-to-buyer proposal transport; never sends customer prices. */
final class FlowtrackPriceProposalDelivery
{
    public function send(int $id): bool
    {
        $proposal = SupplierPriceProposal::query()->find($id);
        if (! $proposal || $proposal->status !== 'pending' || $proposal->delivery_status === 'delivered') return false;
        try {
            $supplier = $proposal->supplier_company_id;
            $company = \App\Models\SupplierCompany::query()->whereKey($supplier)->where('active', true)->firstOrFail();
            $resource = \App\Models\SupplierResource::query()->whereKey($proposal->supplier_resource_id)
                ->where('supplier_company_id', $supplier)->where('type', 'product')
                ->where('status', 'active')->firstOrFail();
            if (! preg_match('/^FT-P-(\\d+)$/', $resource->external_reference, $match)) {
                throw new RuntimeException('Invalid product mapping.');
            }
            $url = rtrim((string) config('flowtrack_bridge.buyer_url'), '/');
            $secret = (string) config('flowtrack_bridge.secret');
            $host = parse_url($url, PHP_URL_HOST);
            $scheme = parse_url($url, PHP_URL_SCHEME);
            if (! config('flowtrack_bridge.enabled') || strlen($secret) < 32 ||
                ! is_string($host) || $host === '' || str_contains($url, '@') ||
                str_contains($url, '?') || str_contains($url, '#') ||
                ! ($scheme === 'https' || ($scheme === 'http' && app()->environment(['local','testing']) && in_array($host, ['localhost', '127.0.0.1'], true)))) {
                throw new RuntimeException('Secure FlowTrack API is not configured.');
            }
            $event = (string) Str::uuid();
            $body = json_encode([
                'event_id' => $event, 'request_uuid' => $proposal->request_uuid,
                'supplier_id' => (int) $company->flowtrack_supplier_id,
                'product_id' => (int) $match[1], 'base_version' => $proposal->base_version,
                'prices' => $proposal->proposed_prices,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
            $time = (string) time();
            $response = Http::connectTimeout(3)->timeout(15)->withoutRedirecting()->withHeaders([
                'X-Supplier-Timestamp' => $time, 'X-Supplier-Event' => $event,
                'X-Supplier-Signature' => hash_hmac('sha256', $time."\n".$event."\n".$body, $secret),
                'Accept' => 'application/json',
            ])->withBody($body, 'application/json')->post($url.'/api/supplier-bridge/price-proposals');
            if (in_array($response->status(), [403, 404, 409, 422], true)) {
                $proposal->update([
                    'status' => 'stale', 'delivery_status' => 'delivered',
                    'review_note' => 'FlowTracker rejected this request because the price, assignment, or validation changed. Refresh the product before resubmitting.',
                    'next_attempt_at' => null,
                ]);
                return false; // Permanent conflict: no endless retry loop.
            }
            if (! $response->successful() || $response->json('received') !== true) {
                throw new RuntimeException('FlowTracker rejected price proposal (HTTP '.$response->status().').');
            }
            $proposal->update(['delivery_status' => 'delivered', 'next_attempt_at' => null]);
            return true;
        } catch (\Throwable $exception) {
            $attempts = min(65535, (int) $proposal->attempts + 1);
            $proposal->update(['attempts' => $attempts, 'delivery_status' => 'pending',
                'next_attempt_at' => now()->addSeconds(min(3600, 30 * (2 ** min($attempts, 7))))]);
            report($exception);
            return false;
        }
    }
}
