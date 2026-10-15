<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class EnsureSupplierActive
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user || !$user->active || !$user->company?->active) {
            Auth::logout();
            $request->session()->invalidate();
            abort(403, 'This supplier account is inactive.');
        }
        if ($user->must_change_password && ! $request->routeIs('supplier.password.change', 'supplier.password.update', 'supplier.logout')) {
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json(['message' => 'Change your temporary password before continuing.'], 423);
            }
            return redirect()->route('supplier.password.change');
        }
        return $next($request);
    }
}
