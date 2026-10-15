<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupplierShipDateAuthorityTest extends TestCase
{
    use RefreshDatabase;

    private static int $sequence = 0;

    private function order(string $key = 'PROD_ISSUE'): array
    {
        $sequence = ++self::$sequence;
        $company = SupplierCompany::create([
            'reference' => 'SHIP-DATE-OWNER-'.$sequence, 'name' => 'Ship Date Owner', 'active' => true,
            'flowtrack_supplier_id' => 91 + $sequence,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id, 'name' => 'Supplier staff',
            'email' => 'supplier-ship-date-'.$sequence.'@example.test',
            'password' => 'LongValidPassword123!', 'active' => true,
        ]);
        $order = SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'order',
            'external_reference' => 'ORDER-TEST-SHIP-'.$sequence, 'status' => 'new',
            'payload' => [
                'revision' => 2, 'source_id' => 201, 'title' => 'Supplier order',
                'supplier_ship_date' => '2026-11-20', 'items' => [], 'documents' => [],
                'workflow' => ['current_phase_id' => 3, 'stages' => [[
                    'id' => 3, 'sequence' => 3, 'name' => 'Production', 'color' => '#008a7a',
                    'state' => 'active', 'state_label' => 'Current', 'dependency' => '',
                    'owner' => 'Your company', 'progress' => 0,
                    'required_total' => 1, 'required_complete' => 0,
                    'tasks' => [[
                        'id' => 407, 'code' => '3.1', 'title' => 'Production issue',
                        'status' => 'Ready', 'mode' => 'active', 'required' => true,
                        'assignee' => 'Your company', 'assigned_to_supplier' => true,
                        'due_date' => null, 'can_act' => true, 'action_key' => $key,
                    ]],
                ]]],
            ],
        ]);
        return [$user, $order];
    }

    public function test_order_details_display_flowtracker_ship_date_without_editable_supplier_date(): void
    {
        [$user, $order] = $this->order();
        $this->actingAs($user)->get(route('supplier.orders.show', $order->id))
            ->assertOk()->assertSee('Supplier ship date')
            ->assertSee('Nov 20, 2026')->assertDontSee('name="supplier_delivery_date"', false)
            ->assertDontSee('name="shipment_date"', false);
    }

    public function test_supplier_cannot_post_dates_even_with_stale_actionable_task_state(): void
    {
        [$user, $order] = $this->order();
        Http::fake();
        foreach (['supplier_delivery_date', 'estimated_delivery_date', 'shipment_date'] as $field) {
            $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]), [
                'decision' => 'confirm', $field => '2026-11-22',
            ])->assertForbidden();
        }
        Http::assertNothingSent();
    }

    public function test_production_monitor_and_dispatch_can_be_confirmed_without_supplier_chosen_dates(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.buyer_url', 'http://127.0.0.1:8000');
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        Http::fake(['http://127.0.0.1:8000/*' => Http::response([
            'received' => true, 'task_status' => 'Completed',
        ], 200)]);
        foreach (['PROD_ISSUE', 'SHIP_PACKAGE'] as $key) {
            [$user, $order] = $this->order($key);
            $request = ['decision' => 'confirm'];
            if ($key === 'SHIP_PACKAGE') {
                $request['carrier'] = 'UPS';
                $request['tracking_number'] = 'TRACK123';
            }
            $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]), $request)
                ->assertRedirect(route('supplier.orders.show', $order->id));
        }
        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            $body = json_decode((string) $request->body(), true);
            $payload = $body['payload'] ?? [];
            return ! isset($payload['supplier_delivery_date'])
                && ! isset($payload['estimated_delivery_date'])
                && ! isset($payload['shipment_date']);
        });
    }

    public function test_old_actionable_date_setting_task_is_refused(): void
    {
        [$user, $order] = $this->order('PROD_SET_ESTIMATED_DELIVERY');
        Http::fake();
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]), [
            'decision' => 'confirm',
        ])->assertForbidden();
        Http::assertNothingSent();
    }
}
