<?php

namespace Tests\Feature;

use Tests\TestCase;

final class SupplierProductDetailEditParityContractTest extends TestCase
{
    public function test_detail_and_edit_use_flowtracker_sections_and_preserve_supplier_navigation(): void
    {
        $detail = file_get_contents(resource_path('views/portal/product-native/product-detail.blade.php'));
        $edit = file_get_contents(resource_path('views/portal/product-native/product-form.blade.php'));
        $editSections = file_get_contents(resource_path('views/portal/product-native/product-form-extra-sections.blade.php'));
        $module = file_get_contents(resource_path('views/portal/product-module.blade.php'));

        foreach (['ft-product-detail-header', 'Product details', 'Classification &amp; availability',
            'Supplier-based pricing', 'Client pricing', 'Certificates &amp; documents'] as $element) {
            $this->assertStringContainsString($element, $detail);
        }
        foreach (['ft-product-form-header', 'Product information', 'Category hierarchy',
            'ft-product-form-shell', 'supplier-native-product-form'] as $element) {
            $this->assertStringContainsString($element, $edit);
        }
        foreach (['Product pricing', 'Product options', 'Shipping urgencies', 'Supporting documents'] as $element) {
            $this->assertStringContainsString($element, $editSections);
        }
        $this->assertStringContainsString("portal.partials.sidebar", $module);
        $this->assertStringContainsString('flowtracker-product-parity.css', $module);
        $this->assertStringNotContainsString('<iframe', $module);
    }

    public function test_product_and_subsection_writes_remain_separately_api_scoped(): void
    {
        $controller = file_get_contents(app_path('Http/Controllers/SupplierProductModuleController.php'));
        $detail = file_get_contents(resource_path('views/portal/product-native/product-detail.blade.php'));
        $form = file_get_contents(resource_path('views/portal/product-native/product-form.blade.php'));
        $pricing = file_get_contents(resource_path('views/portal/product-native/sections/supplier_pricing.blade.php'));

        $this->assertStringContainsString('$bridge->native($request->user()', $controller);
        $this->assertStringContainsString('canEditSubsection', $controller);
        $this->assertStringContainsString("$".'perm(\'supplier_pricing\')', $detail);
        $this->assertStringContainsString('canSaveProduct', $form);
        $this->assertStringContainsString('supplier_currency_table', $pricing);
        $this->assertStringContainsString('base_version', $pricing);
        $this->assertStringNotContainsString('DB::', $controller);
        $this->assertStringNotContainsString('DB::', $detail);
    }
}
