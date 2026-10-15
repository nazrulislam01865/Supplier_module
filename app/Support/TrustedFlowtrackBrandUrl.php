<?php

namespace App\Support;

final class TrustedFlowtrackBrandUrl
{
    public static function favicon(?string $path): ?string
    {
        if (! $path || ! preg_match('#^/branding-assets/favicon/[A-Za-z0-9_-]+\.(?:ico|png|jpe?g|webp)(?:/workspace/[1-9][0-9]*)?$#', $path)) return null;
        return self::originUrl($path);
    }

    public static function resolve(?string $path): ?string
    {
        if (! $path || ! preg_match('#^/branding-assets/logo/[A-Za-z0-9_-]+\.(?:png|jpe?g|webp)(?:/workspace/[1-9][0-9]*)?$#', $path)) return null;
        return self::originUrl($path);
    }

    private static function originUrl(string $path): ?string
    {
        $origin = rtrim((string) config('flowtrack_bridge.buyer_url'), '/');
        $parts = parse_url($origin);
        if (! is_array($parts) || ! in_array($parts['scheme'] ?? '', ['http','https'], true)
            || ! preg_match('/^[a-zA-Z0-9.-]+$/', $parts['host'] ?? '')
            || str_contains($origin, '@') || str_contains($origin, '?') || str_contains($origin, '#')) return null;
        return $origin.$path;
    }
}
