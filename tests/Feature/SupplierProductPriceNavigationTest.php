<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierPriceProposal;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierProductPriceNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(string $name): array
    {
        $company = SupplierCompany::create([
            'reference' => 'SUP-'.$name, 'name' => $name, 'active' => true,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id,
            'name' => $name.' Admin',
            'email' => strtolower($name).'@supplier.example',
            'password' => 'LongValidPassword123!', 'active' => true,
        ]);
        return [$company, $user];
    }

    public function test_guest_is_redirected_to_login_instead_of_seeing_missing_price_page(): void
    {
        $this->get('/products/prices')->assertRedirect('/login');
    }

    public function test_pricing_link_is_in_supplier_sidebar_and_page_keeps_shared_navigation(): void
    {
        [$company, $user] = $this->supplier('Alpha');
        SupplierResource::create([
            'supplier_company_id' => $company->id,
            'type' => 'product', 'status' => 'active',
            'external_reference' => 'FT-P-123',
            'payload' => [
                'name' => 'Alpha Jersey', 'code' => 'SKU-123',
                'supplier_prices' => [['quantity' => 100, 'price' => 2.5]],
            ],
        ]);

        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('Products &amp; Prices', false)
            ->assertSee('href="'.route('supplier.products.prices').'"', false);

        $this->actingAs($user)->get('/products/prices')->assertOk()
            ->assertSee('Alpha Jersey')->assertSee('SKU-123')
            ->assertSee('>View</a>', false)->assertSee('1 tier')
            ->assertSee('Image</th>', false)->assertDontSee('View tiers')
            ->assertDontSee('>Edit prices</a>', false)
            ->assertDontSee('Propose new supplier price tiers')
            ->assertSee('Dashboard')->assertSee('Products &amp; Prices', false)
            ->assertDontSee('Customer selling price:');
    }

    public function test_supplier_price_page_does_not_include_other_company_products(): void
    {
        [$alpha, $alice] = $this->supplier('Alpha');
        [$beta] = $this->supplier('Beta');
        foreach ([[$alpha, 'Alpha Product'], [$beta, 'Private Beta Product']] as [$company, $name]) {
            SupplierResource::create([
                'supplier_company_id' => $company->id,
                'type' => 'product', 'status' => 'active',
                'external_reference' => 'FT-P-'.$company->id,
                'payload' => ['name' => $name, 'supplier_prices' => []],
            ]);
        }

        $this->actingAs($alice)->get('/products/prices')->assertOk()
            ->assertSee('Alpha Product')->assertDontSee('Private Beta Product');
    }

    public function test_edit_prices_requires_same_supplier_and_list_filters_before_pagination(): void
    {
        [$alpha, $alice] = $this->supplier('Alpha');
        [$beta] = $this->supplier('Beta');
        $own = SupplierResource::create([
            'supplier_company_id' => $alpha->id, 'type' => 'product', 'status' => 'active',
            'external_reference' => 'FT-P-901',
            'payload' => ['name' => 'Searchable Jersey', 'code' => 'JER-901',
                'supplier_prices' => [['quantity' => 50, 'price' => 3.75]],
                'price_version' => str_repeat('a', 64)],
        ]);
        $other = SupplierResource::create([
            'supplier_company_id' => $beta->id, 'type' => 'product', 'status' => 'active',
            'external_reference' => 'FT-P-902',
            'payload' => ['name' => 'Searchable Competitor', 'code' => 'CP-902'],
        ]);
        $this->actingAs($alice)->get('/products/prices?q=JER-901')->assertOk()
            ->assertSee('Searchable Jersey')->assertDontSee('Searchable Competitor');
        $this->actingAs($alice)->get('/products/prices?q=NO-MATCH')->assertOk()
            ->assertDontSee('Searchable Jersey')->assertSee('No assigned products match');
        $this->actingAs($alice)->get('/products/prices/'.$own->id)->assertOk()
            ->assertSee('Product Details')->assertSee('Edit Prices')
            ->assertSee('Searchable Jersey')->assertSee('3.75');
        $this->actingAs($alice)->get('/products/prices/'.$other->id)->assertNotFound();
        $this->actingAs($alice)->get('/products/prices/'.$own->id.'/edit')->assertOk()
            ->assertSee('Supplier Price Tiers')->assertSee('Current Approved Price')->assertSee('3.75')
            ->assertSee('Request Price Approval')->assertSee('Save Draft')
            ->assertDontSee('How it works')->assertDontSee('Change <span>(USD)</span>', false);
        $this->actingAs($alice)->get('/products/prices/'.$other->id.'/edit')->assertNotFound();
        $this->actingAs($alice)->post('/products/prices/'.$other->id, ['prices' => '100 1.20'])
            ->assertNotFound();
    }

    public function test_product_and_request_filters_use_only_the_latest_request_for_this_supplier(): void
    {
        [$company, $user] = $this->supplier('Alpha');
        $active = SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'product', 'status' => 'active',
            'external_reference' => 'FT-P-411', 'payload' => ['name' => 'Active Product',
                'code' => 'ACT-411', 'supplier_prices' => [['quantity' => 100, 'price' => 2]]],
        ]);
        SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'product', 'status' => 'inactive',
            'external_reference' => 'FT-P-412', 'payload' => ['name' => 'Inactive Product',
                'code' => 'INACT-412', 'supplier_prices' => []],
        ]);
        foreach (['approved', 'pending'] as $status) {
            SupplierPriceProposal::create([
                'supplier_company_id' => $company->id,
                'supplier_resource_id' => $active->id,
                'supplier_user_id' => $user->id,
                'request_uuid' => (string) \Illuminate\Support\Str::uuid(),
                'base_version' => str_repeat('a', 64),
                'proposed_prices' => [['quantity' => 100, 'price' => 1.75]],
                'status' => $status,
            ]);
        }
        $this->actingAs($user)->get('/products/prices')->assertOk()
            ->assertSee('Total products')->assertSee('Products with pending requests')
            ->assertSee('Approved requests')->assertSee('Last updated')
            ->assertSee('>View</a>', false)->assertSee('Pending approval')
            ->assertSee('Active Product')->assertSee('Inactive Product');
        $this->actingAs($user)->get('/products/prices?request_status=pending')->assertOk()
            ->assertSee('Active Product')->assertDontSee('Inactive Product');
        $this->actingAs($user)->get('/products/prices?request_status=approved')->assertOk()
            ->assertDontSee('Active Product')->assertDontSee('Inactive Product');
        $this->actingAs($user)->get('/products/prices?request_status=none&product_status=inactive')->assertOk()
            ->assertSee('Inactive Product')->assertDontSee('Active Product')
            ->assertSee('>View</a>', false);
    }

    public function test_filters_are_native_get_requests_and_dropdowns_submit_without_extra_api_calls(): void
    {
        [$company, $user] = $this->supplier('Alpha');
        $resource = SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'product', 'status' => 'inactive',
            'external_reference' => 'FT-P-510', 'payload' => ['name' => 'Inactive Hoodie',
                'code' => 'HOOD-510', 'supplier_prices' => []],
        ]);
        SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'product', 'status' => 'active',
            'external_reference' => 'FT-P-511', 'payload' => ['name' => 'Active Shorts',
                'code' => 'SHORT-511', 'supplier_prices' => []],
        ]);
        $this->actingAs($user)->get('/products/prices?product_status=inactive&request_status=none&q=HOOD-510')
            ->assertOk()->assertSee('Inactive Hoodie')->assertDontSee('Active Shorts')
            ->assertSee('Clear filters')->assertSee('supplier-pricing.js');
        $this->actingAs($user)->get('/products/prices?product_status=active&q=HOOD-510')
            ->assertOk()->assertSee('No assigned products match your filters.')
            ->assertDontSee('Inactive Hoodie');
        $this->actingAs($user)->get('/products/prices/'.$resource->id)->assertOk();
    }

    public function test_row_editor_keeps_original_proposal_storage_and_blocks_duplicate_pending_requests(): void
    {
        [$company, $user] = $this->supplier('Alpha');
        $product = SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'product', 'status' => 'active',
            'external_reference' => 'FT-P-515', 'payload' => [
                'name' => 'Prototype Test Item', 'code' => 'ITEM-515',
                'supplier_prices' => [['quantity' => 100, 'price' => 2.5]],
                'price_version' => str_repeat('b', 64),
            ],
        ]);
        // The bridge is intentionally unavailable in this test; delivery is queued for retry.
        config()->set('flowtrack_bridge.enabled', false);
        $tiers = [
            ['quantity' => '100', 'price' => '2.40'],
            ['quantity' => '500', 'price' => '1.75'],
        ];
        $this->actingAs($user)->post('/products/prices/'.$product->id, ['tiers' => $tiers])
            ->assertRedirect(route('supplier.products.prices.edit', $product->id));
        $proposal = SupplierPriceProposal::query()->where('supplier_company_id', $company->id)->firstOrFail();
        $this->assertSame('pending', $proposal->status);
        $this->assertSame(str_repeat('b', 64), $proposal->base_version);
        $this->assertSame([
            ['quantity' => 100, 'price' => 2.4],
            ['quantity' => 500, 'price' => 1.75],
        ], $proposal->proposed_prices);
        $this->assertSame(2.5, (float) $product->fresh()->payload['supplier_prices'][0]['price']);
        $this->actingAs($user)->get('/products/prices/'.$product->id.'/edit')->assertOk()
            ->assertSee('pending FlowTracker approval')->assertDontSee('Save Draft');
        $this->actingAs($user)->get('/products/prices/'.$product->id)->assertOk()
            ->assertSee('Pending approval')->assertDontSee('Edit Prices');
        $this->actingAs($user)->post('/products/prices/'.$product->id, ['tiers' => $tiers])
            ->assertSessionHasErrors('prices');
        $this->assertSame(1, SupplierPriceProposal::query()->count());
    }

    public function test_row_editor_rejects_invalid_and_duplicate_quantity_tiers(): void
    {
        [$company, $user] = $this->supplier('Alpha');
        $product = SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'product', 'status' => 'active',
            'external_reference' => 'FT-P-610',
            'payload' => ['name' => 'Secure Price Editor', 'price_version' => str_repeat('c', 64)],
        ]);
        $this->actingAs($user)->post('/products/prices/'.$product->id, ['tiers' => [
            ['quantity' => '100', 'price' => '1.00'],
            ['quantity' => '100', 'price' => '1.20'],
        ]])->assertSessionHasErrors('prices');
        $this->actingAs($user)->post('/products/prices/'.$product->id, ['tiers' => [
            ['quantity' => '100', 'price' => '1.0000001'],
        ]])->assertSessionHasErrors('tiers.0.price');
        $this->assertSame(0, SupplierPriceProposal::query()->count());
    }

    public function test_product_thumbnail_is_shown_only_for_the_configured_flowtrack_image_origin(): void
    {
        [$company, $user] = $this->supplier('Alpha');
        config()->set('flowtrack_bridge.buyer_url', 'https://flowtrack.example.test');
        $trusted = SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'product', 'status' => 'active',
            'external_reference' => 'FT-P-731',
            'payload' => ['name' => 'Product With Image', 'code' => 'IMG-731',
                'image_url' => 'https://flowtrack.example.test/storage/products/731.jpg', 'supplier_prices' => []],
        ]);
        SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'product', 'status' => 'active',
            'external_reference' => 'FT-P-732',
            'payload' => ['name' => 'External Image Product', 'code' => 'IMG-732',
                'image_url' => 'https://untrusted.example.net/photo.jpg', 'supplier_prices' => []],
        ]);
        $this->actingAs($user)->get('/products/prices')->assertOk()
            ->assertSee('https://flowtrack.example.test/storage/products/731.jpg')
            ->assertDontSee('https://untrusted.example.net/photo.jpg');
        $this->actingAs($user)->get('/products/prices/'.$trusted->id)->assertOk()
            ->assertSee('Product With Image')->assertSee('Edit Prices')
            ->assertSee('https://flowtrack.example.test/storage/products/731.jpg');
    }

    public function test_inactive_product_can_be_viewed_but_cannot_be_edited(): void
    {
        [$company, $user] = $this->supplier('Alpha');
        $product = SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'product', 'status' => 'inactive',
            'external_reference' => 'FT-P-880',
            'payload' => ['name' => 'Inactive Item', 'supplier_prices' => []],
        ]);
        $this->actingAs($user)->get('/products/prices/'.$product->id)->assertOk()
            ->assertSee('Inactive product')->assertDontSee('Edit Prices');
        $this->actingAs($user)->get('/products/prices/'.$product->id.'/edit')->assertNotFound();
    }

}
