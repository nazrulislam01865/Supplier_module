<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SupplierPurchaseOrderListAndOrderDetailRegressionTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(string $reference, int $buyerId): array
    {
        $company = SupplierCompany::create([
            'reference' => $reference,
            'name' => $reference.' Supplier',
            'active' => true,
            'flowtrack_supplier_id' => $buyerId,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id,
            'name' => $reference.' User',
            'email' => strtolower($reference).'@example.test',
            'password' => 'LongValidPassword123!',
            'active' => true,
        ]);
        return [$company, $user];
    }

    private function order(SupplierCompany $company, string $reference, bool $generated): SupplierResource
    {
        return SupplierResource::create([
            'supplier_company_id' => $company->id,
            'type' => 'order',
            'external_reference' => $reference,
            'status' => 'new',
            'payload' => [
                'source_id' => 55,
                'revision' => 100,
                'title' => 'Artwork for '.$reference,
                'created_at' => '2026-11-11T08:00:00+00:00',
                'stage_name' => $generated ? 'Production' : 'Artwork',
                'status' => $generated ? 'In Progress' : 'New',
                'listing' => ['search_text' => strtolower($reference.' Blue Jersey')],
                'items' => [[
                    'source_item_id' => 101,
                    'product_name' => 'Blue Jersey',
                    'quantity' => 10,
                ]],
                'po_creation_available' => ! $generated,
                'purchase_orders' => $generated ? [[
                    'id' => 23,
                    'number' => 'SPO-000055-01',
                    'revision' => 1,
                    'delivery_date' => '2026-11-30',
                    'items' => [[
                        'item_id' => 101, 'name' => 'Blue Jersey', 'sku' => 'JERSEY-1',
                        'quantity' => 10, 'supplier_unit_cost' => 3.5,
                    ]],
                ]] : [],
            ],
        ]);
    }

    public function test_order_detail_compiles_and_renders_purchase_order_link(): void
    {
        [$company, $user] = $this->supplier('OWN', 41);
        $order = $this->order($company, 'ORDER-2026-00464', true);
        $response = $this->actingAs($user)
            ->get(route('supplier.orders.show', $order->id))->assertOk();
        $response->assertSee('View / Edit Purchase Orders (1)')
            ->assertSee(route('supplier.purchase-orders.show', $order->id))
            ->assertSee('Blue Jersey');
    }

    public function test_supplier_purchase_order_list_matches_flowtracker_structure_and_filters(): void
    {
        [$company, $user] = $this->supplier('OWN', 41);
        [$other] = $this->supplier('OTHER', 42);
        $generated = $this->order($company, 'ORDER-2026-00464', true);
        $pending = $this->order($company, 'ORDER-2026-00463', false);
        $this->order($other, 'ORDER-PRIVATE', true);

        $this->actingAs($user)
            ->get(route('supplier.purchase-orders.index'))
            ->assertOk()
            ->assertSee('ft-po-list-heading')
            ->assertSee('ft-po-filter-toolbar')
            ->assertSee('Current stage')
            ->assertSee('PO status')
            ->assertSee('All orders')
            ->assertSee('Not generated')
            ->assertSee('Generated')
            ->assertSee($generated->external_reference)
            ->assertSee($pending->external_reference)
            ->assertDontSee('ORDER-PRIVATE');

        $this->get(route('supplier.purchase-orders.index', ['po_filter' => 'generated']))
            ->assertOk()->assertSee($generated->external_reference)
            ->assertDontSee($pending->external_reference);

        $this->get(route('supplier.purchase-orders.index', ['po_filter' => 'pending']))
            ->assertOk()->assertDontSee($generated->external_reference)
            ->assertSee($pending->external_reference);

        $this->get(route('supplier.purchase-orders.index', ['search' => '00463']))
            ->assertOk()->assertSee($pending->external_reference)
            ->assertDontSee($generated->external_reference);
    }
}
