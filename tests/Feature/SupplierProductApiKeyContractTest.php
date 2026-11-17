<?php

namespace Tests\Feature;

use Tests\TestCase;

final class SupplierProductApiKeyContractTest extends TestCase
{
    public function test_product_api_key_stays_server_side_and_supplier_sidebar_is_retained(): void
    {
        $bridge = file_get_contents(app_path('Services/FlowtrackProductModuleBridge.php'));
        $view = file_get_contents(resource_path('views/portal/product-module.blade.php'));
        $detail = file_get_contents(resource_path('views/portal/product-price-show.blade.php'));
        $matrix = file_get_contents(resource_path('views/portal/partials/pricing-product-detail-matrix.blade.php'));

        $this->assertStringContainsString('flowtrack_bridge.product_api_key', $bridge);
        $this->assertStringContainsString('X-Supplier-Product-Signature', $bridge);
        $this->assertStringContainsString('portal.partials.sidebar', $view);
        $this->assertStringContainsString('$moduleError', $view);
        $this->assertStringContainsString('pricing-product-detail-matrix', $detail);
        foreach (['Quantity', 'Supplier price', 'Remote surcharge', 'Standard shipment', 'Urgent shipment'] as $label) {
            $this->assertStringContainsString($label, $matrix);
        }
        $this->assertStringNotContainsString('flowtrack_bridge.product_api_key', $view);
    }
}
