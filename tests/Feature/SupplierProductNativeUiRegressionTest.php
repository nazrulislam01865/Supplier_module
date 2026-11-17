<?php

namespace Tests\Feature;

use Tests\TestCase;

final class SupplierProductNativeUiRegressionTest extends TestCase
{
    public function test_product_detail_template_has_the_original_sections_and_safe_directive_boundaries(): void
    {
        $detail = file_get_contents(resource_path('views/portal/product-native/product-detail.blade.php'));
        $editor = file_get_contents(resource_path('views/portal/product-native/product-detail-edit-sections.blade.php'));
        $matrix = file_get_contents(resource_path('views/portal/product-native/product-price-matrix-readonly.blade.php'));

        foreach (['Product details', 'Classification &amp; availability', 'Supplier-based pricing',
            'Client pricing', 'Certificates &amp; documents'] as $section) {
            $this->assertStringContainsString($section, $detail);
        }
        $this->assertStringContainsString('ft-product-detail-header', $detail);
        $this->assertStringContainsString('ft-product-detail-matrix', $matrix);
        $this->assertStringContainsString("$".'perm(\'supplier_pricing\')', $detail);
        $this->assertStringNotContainsString('@endforeach@else', $editor);
        $this->assertStringNotContainsString('@endif@endif', $editor);
        $this->assertStringNotContainsString('<iframe', $detail);
    }

    public function test_product_list_uses_the_flowtracker_columns_with_api_based_filters(): void
    {
        $list = file_get_contents(resource_path('views/portal/product-native/products.blade.php'));
        $page = file_get_contents(resource_path('views/portal/product-module.blade.php'));
        $controller = file_get_contents(app_path('Http/Controllers/SupplierProductModuleController.php'));
        foreach (['ft-product-list-table', 'Classification', 'Supplier', 'Size', 'Availability', 'Documents',
            'Status', 'Updated', 'per_page'] as $expected) {
            $this->assertStringContainsString($expected, $list);
        }
        $this->assertStringContainsString('portal.partials.sidebar', $page);
        $this->assertStringContainsString('flowtracker-product-parity.css', $page);
        $this->assertStringContainsString("$".'bridge->native', $controller);
        $this->assertStringNotContainsString('<iframe', $page);
    }
}
