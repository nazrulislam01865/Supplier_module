<?php

namespace Tests\Feature;

use Tests\TestCase;

final class SupplierCategoryPaginationThrottleContractTest extends TestCase
{
    public function test_category_list_has_flowtracker_pagination_without_extra_api_calls(): void
    {
        $view = (string) file_get_contents(resource_path('views/portal/product-native/categories.blade.php'));

        $this->assertStringContainsString("request('cat_per_page', 6)", $view);
        $this->assertStringContainsString('[6, 10, 20, 50]', $view);
        $this->assertStringContainsString("request('cat_page', 1)", $view);
        $this->assertStringContainsString('$filtered->slice(', $view);
        $this->assertStringContainsString('@forelse($pageCategories as $cat)', $view);
        $this->assertStringContainsString('Rows per page', $view);
        $this->assertStringContainsString('Category pagination', $view);
        $this->assertStringContainsString('aria-label="First page"', $view);
        $this->assertStringContainsString('aria-label="Last page"', $view);
        $this->assertStringContainsString('name="cat_q"', $view);
        $this->assertStringContainsString('name="category_status"', $view);
        $this->assertStringContainsString('name="level"', $view);
        $this->assertStringNotContainsString('$bridge->native', $view);
    }

    public function test_product_read_throttle_is_user_scoped_and_writes_keep_their_limit(): void
    {
        $provider = (string) file_get_contents(app_path('Providers/AppServiceProvider.php'));
        $routes = (string) file_get_contents(base_path('routes/web.php'));

        $this->assertStringContainsString("RateLimiter::for('supplier-product-read'", $provider);
        $this->assertStringContainsString("'product-read:user:' . (int) \$request->user()?->id", $provider);
        $this->assertStringContainsString("->middleware('throttle:supplier-product-read')->name('supplier.products.module')", $routes);
        $this->assertStringContainsString("->middleware('throttle:30,1')->name('supplier.products.module.action')", $routes);
        $this->assertStringContainsString("->middleware('throttle:60,1')->name('supplier.products.module.asset')", $routes);
    }
}
