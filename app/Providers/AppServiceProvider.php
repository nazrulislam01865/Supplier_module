<?php
namespace App\Providers;

use App\Services\SupplierSidebarCounts;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as BladeView;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // User + RFQ bucket: neither frequent dashboard refreshes nor staff
        // using the same office IP can throttle an unrelated costing upload.
        RateLimiter::for('supplier-inquiry-task', static fn (Request $request): Limit =>
            Limit::perMinute(20)->by('rfq-task:' . (int) $request->user()?->id . ':' . (int) $request->route('id'))
                ->response(static fn (Request $request, array $headers) => response()->json([
                    'message' => 'This inquiry has received too many task requests. Wait briefly before retrying.',
                ], 429, $headers)));
        RateLimiter::for('supplier-inquiry-task-file', static fn (Request $request): Limit =>
            Limit::perMinute(60)->by('rfq-task-file:' . (int) $request->user()?->id . ':' . (int) $request->route('id')));

        View::composer('portal.partials.sidebar', static function (BladeView $view): void {
            // Workspace pages already provide their counts; standalone pages
            // cannot rely on $quotations, $orderPage or $dashboard being present.
            $data = $view->getData();
            $standalone = ($data['isOrderDetail'] ?? false)
                || ($data['isProductPrices'] ?? false)
                || request()->routeIs('supplier.orders.show', 'supplier.products.prices*');
            if (! $standalone || ! auth()->check()) {
                return;
            }

            $view->with('sidebarCounts', app(SupplierSidebarCounts::class)->forUser(auth()->user()));
        });
    }
}
