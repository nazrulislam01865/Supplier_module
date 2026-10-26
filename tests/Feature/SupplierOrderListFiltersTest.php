<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SupplierOrderListFiltersTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(string $code): array
    {
        $company = SupplierCompany::create([
            'reference' => $code, 'name' => $code.' Supplies', 'active' => true,
            'flowtrack_supplier_id' => $code === 'A' ? 101 : 202,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id,
            'name' => 'Supplier '.$code,
            'email' => 'supplier-'.$code.'@example.test',
            'password' => 'LongValidPassword123!', 'active' => true,
        ]);
        return [$company, $user];
    }

    private function order(SupplierCompany $company, string $ref, string $product, array $listing): SupplierResource
    {
        return SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'order',
            'external_reference' => $ref, 'status' => 'new',
            'payload' => [
                'supplier_ship_date' => '2026-10-21',
                'title' => 'Order '.$ref,
                'created_at' => '2026-10-14T08:00:00Z',
                'order_status' => 'In Progress',
                'items' => [['product_name' => $product, 'quantity' => 100]],
                'listing' => $listing + [
                    'on_hold' => false, 'stage_sequence' => 3,
                    'stage_name' => 'Production', 'stage_color' => '#F28C28',
                    'progress' => 33, 'created_date' => '2026-10-14',
                    'search_text' => strtolower($ref.' '.$product),
                ],
            ],
        ]);
    }

    public function test_filters_are_supplier_scoped_and_preserved_in_pagination(): void
    {
        [$company, $user] = $this->supplier('A');
        [$other] = $this->supplier('B');
        $this->order($company, 'ORDER-2026-0001', 'Blue Jersey', []);
        $this->order($company, 'ORDER-2026-0002', 'Green Cap', [
            'on_hold' => true, 'stage_sequence' => 4,
        ]);
        $this->order($other, 'ORDER-PRIVATE', 'Private Hat', [
        ]);
        for ($i = 3; $i <= 12; $i++) {
            $this->order($company, sprintf('ORDER-2026-%04d', $i), 'Green Cap', []);
        }

        $this->actingAs($user)
            ->get(route('supplier.home', ['view' => 'orders']))
            ->assertOk()->assertSee('Showing 1–10 of 12 orders')
            ->assertSee('All stages')->assertSee('Supplier Delivery Date')
            ->assertSee('Supplier orders by workflow stage')
            ->assertDontSee('ASSIGNED ORDERS')->assertDontSee('ORDERS ON THIS PAGE')
            ->assertDontSee('All clients')->assertDontSee('All owners')
            ->assertDontSee('Owner / Delivery')->assertDontSee('ORDER-PRIVATE');

        $this->get(route('supplier.home', ['view' => 'orders', 'search' => 'Blue Jersey']))
            ->assertOk()->assertSee('ORDER-2026-0001')->assertDontSee('ORDER-2026-0002');
        $this->get(route('supplier.home', ['view' => 'orders', 'hold' => 1]))
            ->assertOk()->assertSee('ORDER-2026-0002')->assertDontSee('ORDER-2026-0001');
        $this->get(route('supplier.home', ['view' => 'orders', 'stage' => 4]))
            ->assertOk()->assertSee('ORDER-2026-0002')->assertDontSee('ORDER-2026-0001');
        $this->get(route('supplier.home', ['view' => 'orders', 'stage' => 3]))
            ->assertOk()->assertDontSee('ORDER-2026-0002')->assertSee('ORDER-2026-0001');
        $this->get(route('supplier.home', ['view' => 'orders', 'from' => '2026-10-15']))
            ->assertOk()->assertSee('No matching orders');
        $this->get(route('supplier.home', ['view' => 'orders', 'to' => '2026-10-14', 'orders_page' => 2]))
            ->assertOk()->assertSee('Showing 11–12 of 12 orders')
            ->assertDontSee('ORDER-PRIVATE');
    }


    public function test_only_relevant_stage_cards_filter_supplier_orders_and_preserve_other_filters(): void
    {
        [$company, $user] = $this->supplier('A');
        [$other] = $this->supplier('B');
        $this->order($company, 'ORDER-ART', 'Artwork Label', ['stage_sequence' => 2]);
        $this->order($company, 'ORDER-PROD-1', 'Production Jersey', ['stage_sequence' => 3]);
        $this->order($company, 'ORDER-PROD-2', 'Production Jersey', ['stage_sequence' => 3]);
        $this->order($company, 'ORDER-QC', 'QC Sleeve', ['stage_sequence' => 4]);
        $this->order($company, 'ORDER-BILL', 'Billing Sample', ['stage_sequence' => 6]);
        $this->order($other, 'ORDER-PRIVATE', 'Private Jersey', ['stage_sequence' => 3]);

        $this->actingAs($user)->get(route('supplier.home', ['view' => 'orders']))
            ->assertOk()
            ->assertSee('Supplier orders by workflow stage')
            ->assertSee('Filter by Production stage, 2 orders')
            ->assertSee('Filter by Artwork stage, 1 order')
            ->assertSee('Filter by QC stage, 1 order')
            ->assertSee('Filter by Shipment stage, 0 orders')
            ->assertSee('Filter by New Order stage, 0 orders')
            ->assertDontSee('Stage 6</span>', false)
            ->assertDontSee('Stage 7</span>', false)
            ->assertDontSee('ORDER-PRIVATE');

        // A selected card keeps its count and can be clicked again to clear stage.
        $this->get(route('supplier.home', ['view' => 'orders', 'stage' => 3]))
            ->assertOk()
            ->assertSee('Clear Production stage, 2 orders')
            ->assertSee('ORDER-PROD-1')
            ->assertDontSee('ORDER-QC')
            ->assertSee('Filter by Artwork stage, 1 order');

        // Cards are counted over all matching orders, not only the current page.
        // A search should narrow the counters but still honor supplier tenancy.
        $this->get(route('supplier.home', ['view' => 'orders', 'search' => 'QC Sleeve']))
            ->assertOk()
            ->assertSee('Filter by QC stage, 1 order')
            ->assertDontSee('Filter by Production stage, 2 orders');
    }

    public function test_stage_cards_replace_summary_cards_and_support_old_workflow_payloads(): void
    {
        [$company, $user] = $this->supplier('A');
        [$other] = $this->supplier('B');
        $legacy = $this->order($company, 'ORDER-LEGACY-QC', 'QC Legacy', ['stage_sequence' => 0]);
        $payload = $legacy->payload;
        $payload['workflow'] = [
            'current_phase_id' => 90,
            'stages' => [[
                'id' => 90, 'sequence' => 4, 'name' => 'QC', 'color' => '#138d7a',
            ]],
        ];
        $legacy->payload = $payload;
        $legacy->save();
        $this->order($company, 'ORDER-PRODUCTION', 'Production Shirt', ['stage_sequence' => 3]);
        $this->order($other, 'ORDER-PRIVATE', 'Private Shirt', ['stage_sequence' => 4]);

        $this->actingAs($user)->get(route('supplier.home', ['view' => 'orders']))
            ->assertOk()
            ->assertSee('Supplier orders by workflow stage')
            ->assertSee('Filter by QC stage, 1 order')
            ->assertSee('Filter by Production stage, 1 order')
            ->assertDontSee('ASSIGNED ORDERS')
            ->assertDontSee('ORDERS ON THIS PAGE')
            ->assertDontSee('ORDER LINES ON THIS PAGE')
            ->assertDontSee('LATEST UPDATE')
            ->assertDontSee('ORDER-PRIVATE');

        $this->get(route('supplier.home', ['view' => 'orders', 'stage' => 4]))
            ->assertOk()
            ->assertSee('Clear QC stage, 1 order')
            ->assertSee('ORDER-LEGACY-QC')
            ->assertDontSee('ORDER-PRODUCTION')
            ->assertDontSee('ORDER-PRIVATE');
    }

    public function test_supplier_stage_cards_remain_visible_when_counts_are_zero(): void
    {
        [$company, $user] = $this->supplier('A');
        $this->order($company, 'ORDER-BILLING-ONLY', 'Billing Shirt', ['stage_sequence' => 6]);

        $this->actingAs($user)->get(route('supplier.home', ['view' => 'orders']))
            ->assertOk()
            ->assertSee('Supplier orders by workflow stage')
            ->assertSee('Filter by Artwork stage, 0 orders')
            ->assertSee('Filter by Production stage, 0 orders')
            ->assertSee('Filter by QC stage, 0 orders')
            ->assertSee('Filter by Shipment stage, 0 orders')
            ->assertSee('Filter by New Order stage, 0 orders')
            ->assertSee('Filter by Completed, 0 orders')
            ->assertDontSee('ASSIGNED ORDERS');
    }


    public function test_new_order_today_and_supplier_completed_cards_use_supplier_tasks_not_buyer_status(): void
    {
        [$company, $user] = $this->supplier('A');
        [$other] = $this->supplier('B');
        $today = now()->toDateString();

        $new = $this->order($company, 'ORDER-NEW-TODAY', 'New Sample', [
            'stage_sequence' => 1,
            'created_date' => $today,
        ]);
        $payload = $new->payload;
        $payload['created_at'] = $today.'T08:00:00Z';
        $new->payload = $payload;
        $new->save();

        $done = $this->order($company, 'ORDER-SUPPLIER-DONE', 'Done Jersey', ['stage_sequence' => 3]);
        $payload = $done->payload;
        $payload['order_status'] = 'In Progress'; // Buyer has not closed the order.
        $payload['workflow'] = ['stages' => [
            ['sequence' => 3, 'tasks' => [
                ['assigned_to_supplier' => true, 'required' => true, 'status' => 'Completed', 'mode' => 'done'],
                ['assigned_to_supplier' => false, 'required' => true, 'status' => 'Ready', 'mode' => 'active'],
            ]],
        ]];
        $done->payload = $payload;
        $done->save();

        $pending = $this->order($company, 'ORDER-SUPPLIER-PENDING', 'Pending Cap', ['stage_sequence' => 3]);
        $payload = $pending->payload;
        $payload['workflow'] = ['stages' => [
            ['sequence' => 3, 'tasks' => [
                ['assigned_to_supplier' => true, 'required' => true, 'status' => 'Ready', 'mode' => 'active'],
            ]],
        ]];
        $pending->payload = $payload;
        $pending->save();

        $this->order($other, 'ORDER-PRIVATE-TODAY', 'Secret', ['created_date' => $today]);

        $this->actingAs($user)->get(route('supplier.home', ['view' => 'orders']))
            ->assertOk()
            ->assertSee('Filter by New Order stage, 1 order')
            ->assertSee('Filter by Created Today, 1 order')
            ->assertSee('Filter by Completed, 1 order')
            ->assertDontSee('ORDER-PRIVATE-TODAY');

        $this->get(route('supplier.home', ['view' => 'orders', 'stage' => 1]))
            ->assertOk()->assertSee('ORDER-NEW-TODAY')->assertDontSee('ORDER-SUPPLIER-DONE');
        $this->get(route('supplier.home', ['view' => 'orders', 'quick' => 'today']))
            ->assertOk()->assertSee('ORDER-NEW-TODAY')->assertDontSee('ORDER-SUPPLIER-DONE')
            ->assertSee('Clear Created Today, 1 order');
        $this->get(route('supplier.home', ['view' => 'orders', 'quick' => 'completed']))
            ->assertOk()->assertSee('ORDER-SUPPLIER-DONE')->assertDontSee('ORDER-SUPPLIER-PENDING')
            ->assertDontSee('ORDER-NEW-TODAY')->assertSee('Clear Completed, 1 order');
        $this->get(route('supplier.home', ['view' => 'orders', 'stage' => 3, 'quick' => 'completed']))
            ->assertOk()->assertSee('ORDER-SUPPLIER-DONE')->assertSee('ORDER-SUPPLIER-PENDING');
    }

    public function test_supplier_date_and_product_display_do_not_use_client_or_customer_eta(): void
    {
        [$company, $user] = $this->supplier('A');
        $resource = $this->order($company, 'ORDER-2026-0001', 'Custom Jersey', []);
        $payload = $resource->payload;
        $payload['title'] = 'PRIVATE CUSTOMER — Sample Order';
        $payload['estimated_delivery_date'] = '2026-12-20';
        $payload['delivery_date'] = '2026-12-20';
        $payload['supplier_ship_date'] = null;
        $resource->payload = $payload;
        $resource->save();

        $this->actingAs($user)->get(route('supplier.home', ['view' => 'orders']))
            ->assertOk()->assertSee('Custom Jersey')->assertSee('Not set')
            ->assertDontSee('PRIVATE CUSTOMER')->assertDontSee('Dec 20, 2026');

        $payload['supplier_ship_date'] = '2026-11-04';
        $resource->payload = $payload;
        $resource->save();
        $this->get(route('supplier.home', ['view' => 'orders']))
            ->assertOk()->assertSee('Nov 4, 2026')->assertSee('33%');
    }

    public function test_legacy_orders_remain_visible_until_synced(): void
    {
        [$company, $user] = $this->supplier('A');
        SupplierResource::create([
            'supplier_company_id' => $company->id,
            'type' => 'order', 'status' => 'new',
            'external_reference' => 'ORDER-LEGACY',
            'payload' => ['title' => 'Order ORDER-LEGACY', 'items' => [
                ['product_name' => 'Legacy Shirt'],
            ]],
        ]);
        $this->actingAs($user)->get(route('supplier.home', ['view' => 'orders']))
            ->assertOk()->assertSee('ORDER-LEGACY')->assertSee('Legacy Shirt');
    }
}
