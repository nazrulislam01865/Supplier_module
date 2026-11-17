<?php

namespace App\Services;

use App\Models\SupplierUser;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/** Supplier authenticated on portal; permissions are resolved by FlowTracker itself. */
final class FlowtrackProductModuleBridge
{
    private function credentials(): array
    {
        $url = rtrim((string) config('flowtrack_bridge.buyer_url'), '/');
        $secret = (string) config('flowtrack_bridge.product_api_key');
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        if (! config('flowtrack_bridge.enabled') || strlen($secret) < 32
            || ! is_string($host) || $host === '' || str_contains($url, '@')
            || str_contains($url, '?') || str_contains($url, '#')
            || ! ($scheme === 'https' || ($scheme === 'http' && app()->environment(['local', 'testing'])
                && in_array($host, ['localhost', '127.0.0.1'], true)))) {
            throw new \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException(null, 'Secure Product API connection is not configured. Check the API key and FlowTracker URL.');
        }
        return [$url, $secret];
    }

    private function send(string $action, array $payload): array
    {
        [$url, $secret] = $this->credentials();
        $event = (string) Str::uuid();
        $body = json_encode(['event_id' => $event] + $payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $time = (string) time();
        $response = Http::connectTimeout(3)->timeout(12)->withoutRedirecting()->withHeaders([
            'X-Supplier-Product-Timestamp' => $time,
            'X-Supplier-Product-Event' => $event,
            'X-Supplier-Product-Signature' => hash_hmac('sha256', $time."\n".$event."\n".$body, $secret),
            'Accept' => 'application/json',
        ])->withBody($body, 'application/json')->post($url.'/api/supplier-bridge/product-module/'.$action);
        if ($response->status() === 403 || $response->status() === 404) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException('FlowTracker has not granted this supplier Product module access.');
        }
        if (! $response->successful()) {
            $status = $response->status();
            if ($status === 401) throw new \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException(null, 'FlowTracker rejected the Product API key. Verify that both applications use the same key.');
            if ($status === 503) throw new \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException(null, 'FlowTracker Product access is not configured for this environment. Check the Product module URL and admin hostname.');
            throw new \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException(null, 'FlowTracker Product API is temporarily unavailable (HTTP '.$status.').');
        }
        return (array) $response->json();
    }

    public function launch(SupplierUser $user, string $group): string
    {
        $company = $user->company;
        if (! $user->active || ! $company || ! $company->active || ! $company->flowtrack_supplier_id
            || $user->must_change_password || ! in_array($group, ['product', 'product_category'], true)) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException();
        }
        $result = $this->send('issue', [
            'supplier_id' => (int) $company->flowtrack_supplier_id,
            'supplier_user_id' => (int) $user->id,
            'supplier_user_name' => (string) $user->name,
            'group' => $group,
        ]);
        $launch = (string) ($result['launch_url'] ?? '');
        // Only the configured FlowTracker origin can be used for a delegated redirect.
        $url = rtrim((string) config('flowtrack_bridge.buyer_url'), '/');
        $expected = parse_url((string) config('flowtrack_bridge.product_module_url', $url));
        $actual = parse_url($launch);
        if (! is_array($expected) || ! is_array($actual)
            || ($expected['host'] ?? null) !== ($actual['host'] ?? null)
            || ($expected['scheme'] ?? null) !== ($actual['scheme'] ?? null)
            || (int) ($expected['port'] ?? 443) !== (int) ($actual['port'] ?? 443)
            || ! preg_match('~^/supplier-product/access/[a-zA-Z0-9]{64}$~', (string) ($actual['path'] ?? ''))
            || isset($actual['query']) || isset($actual['fragment']) || isset($actual['user'])) {
            throw new \Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException(null, 'The Product API returned a URL outside the configured Product module origin.');
        }
        return $launch;
    }

    public function revoke(SupplierUser $user): void
    {
        if (! $user->company?->flowtrack_supplier_id) return;
        $this->send('revoke', [
            'supplier_id' => (int) $user->company->flowtrack_supplier_id,
            'supplier_user_id' => (int) $user->id,
        ]);
    }
    /** Native Supplier Portal pages: all product operations are executed by FlowTracker. */
    public function native(SupplierUser $user, string $action, array $payload = []): array
    {
        $company = $user->company;
        if (! $user->active || ! $company || ! $company->active || ! $company->flowtrack_supplier_id
            || $user->must_change_password) {
            throw new \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException(
                'The supplier account is not linked to an active FlowTracker supplier.'
            );
        }
        [$url, $secret] = $this->credentials();
        $event = (string) Str::uuid();
        $body = json_encode([
            'event_id' => $event,
            'supplier_id' => (int) $company->flowtrack_supplier_id,
            'supplier_user_id' => (int) $user->id,
            'action' => $action,
        ] + $payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
        $time = (string) time();
        $response = Http::connectTimeout(3)->timeout(30)->withoutRedirecting()
            ->withHeaders([
                'X-Supplier-Product-Timestamp' => $time,
                'X-Supplier-Product-Event' => $event,
                'X-Supplier-Product-Signature' => hash_hmac('sha256', $time."\n".$event."\n".$body, $secret),
                'Accept' => 'application/json',
            ])
            ->withBody($body, 'application/json')
            ->post($url.'/api/supplier-bridge/product-module/data');
        if (! $response->successful()) {
            $code = $response->status();
            $message = (string) $response->json('message', 'FlowTracker rejected the Product operation.');
            if ($code === 422) {
                $errors = $response->json('errors', []);
                $message = is_array($errors) && $errors !== []
                    ? (string) collect($errors)->flatten()->first() : $message;
            }
            if ($code === 401) $message = 'FlowTracker rejected the Product API key. Verify the key in both .env files.';
            if ($code >= 500) $message = 'FlowTracker Product API is unavailable. Check its Laravel log.';
            throw new \Symfony\Component\HttpKernel\Exception\HttpException(
                in_array($code, [401, 403, 404, 409, 413, 422, 429, 503], true) ? $code : 502,
                $message
            );
        }
        $json = $response->json();
        if (! is_array($json) || ! is_array($json['data'] ?? null)) {
            throw new RuntimeException('FlowTracker Product API returned an invalid data envelope.');
        }
        return $json['data'];
    }

}
