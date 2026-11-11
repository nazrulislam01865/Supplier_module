<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use App\Services\FlowtrackPurchaseOrderDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupplierPurchaseOrderSyncTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(int $number): array
    {
        $company = SupplierCompany::create([
            'reference' => 'PO-S'.$number, 'name' => 'Supplier '.$number, 'active' => true,
            'flowtrack_supplier_id' => $number,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id, 'name' => 'Supplier '.$number,
            'email' => 'po'.$number.'@supplier.example', 'password' => 'LongValidPassword123!', 'active' => true,
        ]);
        return [$company, $user];
    }

    private function order(SupplierCompany $company): SupplierResource
    {
        return SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'order',
            'external_reference' => 'ORD-PO-55', 'status' => 'new',
            'payload' => [
                'title' => 'Artwork PO', 'revision' => 100, 'source_id' => 55,
                'items' => [[
                    'source_item_id' => 77, 'product_name' => 'Sample Product',
                    'product_code' => 'PRD-000077', 'sku' => 'MODEL-1', 'quantity' => 20, 'unit_price' => 4.25,
                ]],
                'po_creation_available' => true, 'purchase_orders' => [],
                'documents' => [['id' => 998, 'kind' => 'tracking_pdf', 'name' => 'tracking.pdf', 'size' => 19]],
            ],
        ]);
    }

    public function test_purchase_order_pages_are_supplier_scoped_and_show_tracking_pdf(): void
    {
        [$company, $user] = $this->supplier(41);
        [, $other] = $this->supplier(42);
        $order = $this->order($company);
        $this->actingAs($other)->get(route('supplier.purchase-orders.show', $order->id))->assertNotFound();
        $this->actingAs($user)->get(route('supplier.purchase-orders.show', $order->id))
            ->assertOk()->assertSee('Create Purchase Order')->assertSee('Artwork Tracking PDF')
            ->assertSee('MODEL-1');
    }

    public function test_invalid_or_foreign_po_item_ids_are_rejected_before_a_bridge_request(): void
    {
        [$company, $user] = $this->supplier(41);
        $order = $this->order($company);
        Http::fake();
        $this->actingAs($user)->post(route('supplier.purchase-orders.save', $order->id), [
            'items' => [['item_id' => 999, 'name' => 'Other supplier product',
                'sku' => 'MODEL-X', 'quantity' => 1, 'supplier_unit_cost' => '5.00']],
        ])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_po_mutation_posts_signed_json_and_shows_accepted_revision(): void
    {
        [$company, $user] = $this->supplier(41);
        $order = $this->order($company);
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.buyer_url', 'https://flowtracker.example');
        config()->set('flowtrack_bridge.secret', str_repeat('t', 40));
        Http::fake(['flowtracker.example/api/supplier-bridge/purchase-orders' => Http::response([
            'received' => true,
            'po' => ['id' => 81, 'number' => 'SPO-000055-01', 'revision' => 1,
                'delivery_date' => '2026-11-25', 'status' => 'not_sent',
                'items' => [['item_id' => 77, 'name' => 'Sample Product', 'sku' => 'MODEL-1',
                    'quantity' => 18, 'supplier_unit_cost' => 4.25]],
                'document' => ['id' => 190, 'name' => 'SPO-000055-01.pdf', 'size' => 500]],
        ], 200)]);
        $this->actingAs($user)->post(route('supplier.purchase-orders.save', $order->id), [
            'delivery_date' => '2026-11-25', 'items' => [[
                'item_id' => 77, 'name' => 'Sample Product', 'sku' => 'MODEL-1',
                'quantity' => 18, 'supplier_unit_cost' => '4.25',
            ]],
        ])->assertRedirect();
        Http::assertSent(static function ($request) {
            $body = $request->body();
            $timestamp = $request->header('X-Supplier-Timestamp');
            $timestamp = is_array($timestamp) ? ($timestamp[0] ?? '') : (string) $timestamp;
            $event = $request->header('X-Supplier-Event');
            $event = is_array($event) ? ($event[0] ?? '') : (string) $event;
            $actual = $request->header('X-Supplier-Signature');
            $actual = is_array($actual) ? ($actual[0] ?? '') : (string) $actual;
            $expected = hash_hmac('sha256', $timestamp."\n".$event."\n".$body, str_repeat('t', 40));
            return hash_equals($expected, $actual) && $request->data()['supplier_id'] === 41
                && $request->data()['order_id'] === 55 && $request->data()['action'] === 'create';
        });
        $order->refresh();
        $this->assertSame('SPO-000055-01', $order->payload['purchase_orders'][0]['number']);
        $this->assertFalse($order->payload['po_creation_available']);
    }
    public function test_split_creation_sends_only_owned_product_ids_and_receives_the_complete_po_batch(): void
    {
        [$company, $user] = $this->supplier(41);
        $order = $this->order($company);
        $data = $order->payload;
        $data['items'][] = [
            'source_item_id' => 78, 'product_name' => 'Second Product',
            'product_code' => 'PRD-000078', 'sku' => 'MODEL-2', 'quantity' => 10,
            'unit_price' => 7.5,
        ];
        $order->payload = $data;
        $order->save();
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.buyer_url', 'https://flowtracker.example');
        config()->set('flowtrack_bridge.secret', str_repeat('t', 40));
        $records = [
            ['id' => 81, 'number' => 'SPO-000055-01', 'revision' => 1,
                'delivery_date' => null, 'status' => 'not_sent',
                'items' => [['item_id' => 77, 'name' => 'Sample Product', 'sku' => 'MODEL-1', 'quantity' => 20, 'supplier_unit_cost' => 4.25]],
                'document' => ['id' => 190, 'name' => 'SPO-000055-01.pdf', 'size' => 500]],
            ['id' => 82, 'number' => 'SPO-000055-02', 'revision' => 1,
                'delivery_date' => null, 'status' => 'not_sent',
                'items' => [['item_id' => 78, 'name' => 'Second Product', 'sku' => 'MODEL-2', 'quantity' => 10, 'supplier_unit_cost' => 7.5]],
                'document' => ['id' => 191, 'name' => 'SPO-000055-02.pdf', 'size' => 510]],
        ];
        Http::fake(['flowtracker.example/api/supplier-bridge/purchase-orders' =>
            Http::response(['received' => true, 'po' => $records[0], 'pos' => $records], 200)]);
        $this->actingAs($user)->post(route('supplier.purchase-orders.save', $order->id), [
            'mode' => 'split', 'split_item_ids' => [77],
            'items' => [
                ['item_id' => 77, 'name' => 'Sample Product', 'sku' => 'MODEL-1', 'quantity' => 20, 'supplier_unit_cost' => '4.25'],
                ['item_id' => 78, 'name' => 'Second Product', 'sku' => 'MODEL-2', 'quantity' => 10, 'supplier_unit_cost' => '7.50'],
            ],
        ])->assertRedirect();
        Http::assertSent(fn ($request) => $request->data()['mode'] === 'split'
            && $request->data()['split_item_ids'] === [77]
            && count($request->data()['items']) === 2);
        $order->refresh();
        $this->assertCount(2, $order->payload['purchase_orders']);
        $this->assertFalse($order->payload['po_creation_available']);
    }

}
