<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierPriceTierDisclosureTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_can_open_own_full_price_table_in_modal_on_list_and_view_page(): void
    {
        $company = SupplierCompany::create(['reference' => 'PRICE-SCOPE-A', 'name' => 'Supplier A', 'active' => true]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id, 'name' => 'Supplier A User',
            'email' => 'a@price-scope.test', 'password' => 'ValidPassword123!', 'active' => true,
        ]);
        $product = SupplierResource::create([
            'supplier_company_id' => $company->id, 'external_reference' => 'FT-P-871',
            'type' => 'product', 'status' => 'active',
            'payload' => ['name' => 'Scoped Product', 'code' => 'PRD-871',
                'supplier_prices' => [
                    ['quantity' => 1, 'price' => 9.15],
                    ['quantity' => 100, 'price' => 8.35],
                    ['quantity' => 500, 'price' => 7.20],
                ],
                'price_version' => str_repeat('a', 64),
            ],
        ]);
        $other = SupplierCompany::create(['reference' => 'PRICE-SCOPE-B', 'name' => 'Supplier B', 'active' => true]);
        SupplierResource::create([
            'supplier_company_id' => $other->id, 'external_reference' => 'FT-P-872',
            'type' => 'product', 'status' => 'active',
            'payload' => ['name' => 'Private Supplier Product', 'supplier_prices' => [
                ['quantity' => 1, 'price' => 999.99],
            ]],
        ]);

        $this->actingAs($user)->get('/products/prices')->assertOk()
            ->assertSee('View price table')
            ->assertSee('supplierPriceTableDialog')
            ->assertSee('pricing-tier-template-'.$product->id)
            ->assertDontSee('Show price table')
            ->assertDontSee('pricing-inline-tiers')
            ->assertSee('9.15')->assertSee('8.35')->assertSee('7.20')
            ->assertDontSee('Private Supplier Product')->assertDontSee('999.99');
        $this->actingAs($user)->get('/products/prices/'.$product->id)->assertOk()
            ->assertSee('Supplier-specific prices approved in FlowTracker')
            ->assertSee('9.15')->assertSee('8.35')->assertSee('7.20')
            ->assertDontSee('999.99');
    }

    public function test_supplier_without_synced_specific_tiers_sees_no_price_fallback(): void
    {
        $company = SupplierCompany::create(['reference' => 'PRICE-EMPTY', 'name' => 'No Price Supplier', 'active' => true]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id, 'name' => 'User',
            'email' => 'empty@price-scope.test', 'password' => 'ValidPassword123!', 'active' => true,
        ]);
        $product = SupplierResource::create([
            'supplier_company_id' => $company->id, 'external_reference' => 'FT-P-991',
            'type' => 'product', 'status' => 'active',
            'payload' => ['name' => 'Unpriced Product', 'supplier_prices' => []],
        ]);

        $this->actingAs($user)->get('/products/prices')->assertOk()
            ->assertSee('No supplier-specific price assigned')->assertDontSee('data-price-table-trigger');
        $this->actingAs($user)->get('/products/prices/'.$product->id)->assertOk()
            ->assertSee('No supplier-specific price has been assigned in FlowTracker.');
    }
}
