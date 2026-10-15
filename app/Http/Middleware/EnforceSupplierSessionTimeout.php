<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceSupplierSessionTimeout
{
    private const IDLE_SECONDS = 30 * 60;
    private const LAST_ACTIVITY = 'supplier_last_activity_at';

    public function handle(Request $request, Closure $next): Response
    {
        $session = $request->session();
        $lastActivity = $session->get(self::LAST_ACTIVITY);
        $now = now()->timestamp;

        // A legacy "remember me" cookie must never silently reinstate an expired session.
        if (($lastActivity === null && Auth::viaRemember())
            || ($lastActivity !== null && ($now - (int) $lastActivity) >= self::IDLE_SECONDS)) {
            Auth::logout();
            $session->invalidate();
            $session->regenerateToken();

            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Your supplier session expired. Please sign in again.'], 401);
            }
            return redirect()->route('supplier.login')->with('supplier_session_expired', true);
        }

        // Dashboard/catalog/order refreshes are autonomous; they must not reset the idle clock.
        if (! $request->routeIs(
            'supplier.dashboard.revision',
            'supplier.dashboard.refresh',
            'supplier.dashboard.realtime.auth',
            'supplier.dashboard.notifications.center',
            'supplier.products.prices.realtime.revision',
            'supplier.products.prices.realtime.auth',
            'supplier.orders.realtime.revision',
            'supplier.orders.realtime.auth'
        )) {
            $session->put(self::LAST_ACTIVITY, $now);
        }

        return $next($request);
    }
}
