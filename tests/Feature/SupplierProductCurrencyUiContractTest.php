<?php

namespace Tests\Feature;

use Tests\TestCase;

final class SupplierProductCurrencyUiContractTest extends TestCase
{
    public function test_supplier_product_editor_uses_original_product_layout_and_api_forms(): void
    {
        $page = (string) file_get_contents(resource_path('views/portal/product-module.blade.php'));
        $form = (string) file_get_contents(resource_path('views/portal/product-native/product-form.blade.php'));
        $extra = (string) file_get_contents(resource_path('views/portal/product-native/product-form-extra-sections.blade.php'));
        $pricing = (string) file_get_contents(resource_path('views/portal/product-native/sections/supplier_pricing.blade.php'));

        $this->assertStringContainsString("portal.partials.sidebar", $page);
        $this->assertStringContainsString('flowtracker-product-parity.css', $page);
        $this->assertStringContainsString('ft-product-form-shell', $form);
        foreach (['Product pricing', 'Product options', 'Shipping urgencies', 'Supporting documents'] as $section) {
            $this->assertStringContainsString($section, $extra);
        }
        $this->assertStringContainsString('supplier_currency_table', $pricing);
        $this->assertStringContainsString('save_pricing', $pricing);
        $this->assertStringNotContainsString('<iframe', $page);
        $this->assertStringNotContainsString('DB::', $pricing);
    }
}
