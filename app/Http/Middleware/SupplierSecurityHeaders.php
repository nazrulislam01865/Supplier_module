<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SupplierSecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = base64_encode(random_bytes(16));
        $request->attributes->set('csp_nonce', $nonce);
        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        // Permit only this application's configured Reverb WebSocket origin.
        // Credentials and arbitrary untrusted hosts never enter a CSP directive.
        $host = (string) config('supplier_realtime.host', '');
        $port = (int) config('supplier_realtime.port', 443);
        $scheme = config('supplier_realtime.scheme') === 'https' ? 'wss' : 'ws';
        $connectSrc = "'self'";
        if (preg_match('/^[a-zA-Z0-9.-]+$/', $host) && $port > 0 && $port <= 65535) {
            $connectSrc .= ' '.$scheme.'://'.$host.':'.$port;
        }
        $imageSrc = "'self' data:";
        $buyer = parse_url((string) config('flowtrack_bridge.buyer_url'));
        if (is_array($buyer) && in_array($buyer['scheme'] ?? '', ['http', 'https'], true)
            && preg_match('/^[a-zA-Z0-9.-]+$/', $buyer['host'] ?? '')
            && (! isset($buyer['port']) || ($buyer['port'] > 0 && $buyer['port'] <= 65535))) {
            $imageSrc .= ' '.$buyer['scheme'].'://'.$buyer['host'].(isset($buyer['port']) ? ':'.$buyer['port'] : '');
        }
        $response->headers->set('Content-Security-Policy', "default-src 'self'; base-uri 'self'; form-action 'self'; object-src 'none'; frame-ancestors 'none'; img-src {$imageSrc}; style-src 'self' 'unsafe-inline'; script-src 'self' 'nonce-{$nonce}'; connect-src {$connectSrc}");
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        return $response;
    }
}
