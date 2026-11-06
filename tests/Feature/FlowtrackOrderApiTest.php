<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FlowtrackOrderApiTest extends TestCase
{
    use RefreshDatabase;

    private function data(int $supplierId): array
    {
        return [
            'event_id' => 'c09541f5-1bc7-4564-abd6-0d40ec325d21',
            'supplier_id' => $supplierId,
            'order' => [
                'reference' => 'ORDER-2026-0099',
                'title' => 'Assigned order',
                'created_at' => '2026-10-09T08:00:00+00:00',
                'delivery_date' => '2026-10-20',
                'status' => 'Completed',
                'supplier_instruction' => 'Use approved packaging.',
                'items' => [['product_name' => 'Bag', 'product_code' => 'PRD-000123', 'quantity' => 200]],
            ],
        ];
    }

    private function headers(string $body): array
    {
        $timestamp = (string) time();
        $event = 'c09541f5-1bc7-4564-abd6-0d40ec325d21';
        return [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FLOWTRACK_TIMESTAMP' => $timestamp,
            'HTTP_X_FLOWTRACK_EVENT' => $event,
            'HTTP_X_FLOWTRACK_SIGNATURE' => hash_hmac('sha256', $timestamp."\n".$event."\n".$body, 'test-bridge-secret-at-least-32-characters'),
        ];
    }


    public function test_signed_supplier_list_fields_use_buyer_set_delivery_stage_and_progress(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        $company = SupplierCompany::create([
            'reference' => 'SUP001', 'name' => 'One', 'active' => true, 'flowtrack_supplier_id' => 101,
        ]);
        $data = $this->data(101);
        $data['order']['stage_sequence'] = 3;
        $data['order']['stage_name'] = 'Production';
        $data['order']['stage_color'] = '#f28c28';
        $data['order']['progress'] = 43;
        $data['order']['on_hold'] = true;
        $data['order']['supplier_ship_date'] = '2026-11-10';
        $data['order']['estimated_delivery_date'] = '2026-12-20';
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)
            ->assertOk();

        $saved = SupplierResource::where('supplier_company_id', $company->id)->where('type', 'order')->firstOrFail()->payload;
        $this->assertSame(3, $saved['listing']['stage_sequence']);
        $this->assertSame(43, $saved['listing']['progress']);
        $this->assertTrue($saved['listing']['on_hold']);
        $this->assertSame('2026-11-10', $saved['supplier_ship_date']);
        $this->assertArrayNotHasKey('client_code', $saved['listing']);
        $this->assertArrayNotHasKey('owner_name', $saved['listing']);
    }

    public function test_signed_delivery_is_isolated_and_idempotent(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        $a = SupplierCompany::create(['reference' => 'SUP001', 'name' => 'One', 'active' => true, 'flowtrack_supplier_id' => 101]);
        $b = SupplierCompany::create(['reference' => 'SUP002', 'name' => 'Two', 'active' => true, 'flowtrack_supplier_id' => 202]);
        $other = SupplierUser::create(['supplier_company_id' => $b->id, 'name' => 'Other', 'email' => 'other@example.test', 'password' => 'SomeLongPassword12', 'active' => true]);
        $body = json_encode($this->data(101), JSON_THROW_ON_ERROR);
        $headers = $this->headers($body);

        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $headers, $body)->assertOk();
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $headers, $body)->assertOk();
        $this->assertSame(1, SupplierResource::where('type', 'order')->count());
        $this->assertSame($a->id, SupplierResource::where('type', 'order')->first()->supplier_company_id);
        $this->assertSame('Completed', SupplierResource::where('type', 'order')->first()->payload['order_status']);
        $this->assertSame('PRD-000123', SupplierResource::where('type', 'order')->first()->payload['items'][0]['product_code']);
        $this->actingAs($other)->get('/')->assertDontSee('ORDER-2026-0099');
    }

    public function test_replay_updates_order_details_and_never_persists_client_data(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        $company = SupplierCompany::create(['reference' => 'SUP001', 'name' => 'One', 'active' => true, 'flowtrack_supplier_id' => 101]);
        $first = $this->data(101);
        $first['order']['client_email'] = 'must-not-be-saved@example.test';
        $first['order']['items'][0]['client_name'] = 'Private Customer';
        $body = json_encode($first, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)->assertOk();
        $updated = $this->data(101);
        $updated['order']['items'][0]['quantity'] = 250;
        $updatedBody = json_encode($updated, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($updatedBody), $updatedBody)->assertOk();
        $this->assertSame(1, SupplierResource::where('supplier_company_id', $company->id)->where('type', 'order')->count());
        $payload = SupplierResource::where('type', 'order')->first()->payload;
        $this->assertSame(250, $payload['items'][0]['quantity']);
        $this->assertArrayNotHasKey('client_name', $payload['items'][0]);
        $this->assertArrayNotHasKey('client_email', $payload);
    }

    public function test_signed_order_projection_preserves_real_supplier_pricing_tasks_and_artwork_metadata(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        $company = SupplierCompany::create([
            'reference' => 'SUP001', 'name' => 'One', 'active' => true, 'flowtrack_supplier_id' => 101,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id, 'name' => 'Supplier User',
            'email' => 'supplier-user@example.test', 'password' => 'LongValidPassword12', 'active' => true,
        ]);
        $data = $this->data(101);
        $data['order']['title'] = 'Assigned custom shirt production';
        $data['order']['currency'] = 'GBP';
        $data['order']['items'][0]['unit_price'] = 2.75;
        $data['order']['stages'] = [
            ['name' => 'Artwork', 'status' => 'completed', 'updated_at' => '2026-10-09T09:00:00Z',
                'tasks' => [
                    ['name' => 'Artwork approved', 'status' => 'completed', 'updated_at' => '2026-10-09T09:00:00Z'],
                    ['name' => 'Sample approval', 'status' => 'skipped'],
                ]],
            ['name' => 'Production', 'status' => 'in_progress', 'tasks' => [
                ['name' => 'Start Production', 'status' => 'in_progress', 'updated_at' => '2026-10-09T11:00:00Z'],
            ]],
            ['name' => 'Quality control', 'status' => 'upcoming', 'tasks' => []],
        ];
        $data['order']['documents'] = [[
            'id' => 23, 'name' => 'artwork.pdf', 'size' => 1024,
            'version' => 3, 'uploaded_at' => '2026-10-09T08:00:00Z',
        ]];
        $data['order']['recent_updates'] = [
            ['title' => 'Artwork approved · Completed', 'status' => 'completed', 'date' => '2026-10-09T09:00:00Z'],
            ['title' => 'Order created', 'status' => 'created', 'date' => '2026-10-09T08:00:00Z'],
        ];
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)->assertOk();

        $saved = SupplierResource::where('supplier_company_id', $company->id)->where('type', 'order')->firstOrFail();
        $this->assertSame(2.75, $saved->payload['items'][0]['unit_price']);
        $this->assertSame('GBP', $saved->payload['currency']);
        $this->assertSame('skipped', $saved->payload['stages'][0]['tasks'][1]['status']);
        $this->assertSame(3, $saved->payload['documents'][0]['version']);
        $this->assertCount(2, $saved->payload['recent_updates']);
        $updated = $data;
        $updated['order']['revision'] = 200;
        $updated['order']['stages'][1]['status'] = 'completed';
        $body = json_encode($updated, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)->assertOk();
        $saved->refresh();
        $this->assertSame('Production · Completed', $saved->payload['recent_updates'][0]['title']);
        // Legacy skipped tasks remain in the received snapshot; the detail page
        // displays the canonical workflow, not the legacy stages task list.
        $this->assertSame('skipped', $saved->payload['stages'][0]['tasks'][1]['status']);
        $this->actingAs($user)->get('/orders/'.$saved->id)
            ->assertOk()->assertSee('£2.75')->assertSee('£550.00')
            ->assertSee('Artwork approved')->assertSee('V3')
            ->assertDontSee('Overall status')->assertDontSee('Reference number')
            ->assertDontSee('Shipping address');
    }

    public function test_unsigned_and_tampered_requests_are_rejected(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        $body = json_encode($this->data(101), JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertUnauthorized();
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body.' ')->assertUnauthorized();
        $this->assertSame(0, SupplierResource::count());
    }

    public function test_unmapped_supplier_cannot_receive_order(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        $body = json_encode($this->data(999), JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)->assertStatus(409);
    }
    public function test_out_of_order_revision_cannot_restore_old_supplier_files_or_assignment(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        $company = SupplierCompany::create(['reference' => 'SUP001', 'name' => 'One', 'active' => true, 'flowtrack_supplier_id' => 101]);
        $latest = $this->data(101);
        $latest['order']['revision'] = 400;
        $latest['order']['source_id'] = 99;
        $latest['order']['stages'] = [
            ['name' => 'Artwork', 'status' => 'completed', 'tasks' => [['name' => 'Artwork approval', 'status' => 'completed']]],
            ['name' => 'Production', 'status' => 'in_progress', 'tasks' => [['name' => 'Production started', 'status' => 'completed']]],
            ['name' => 'Quality control', 'status' => 'upcoming', 'tasks' => [['name' => 'Quality check', 'status' => 'upcoming']]],
        ];
        $latest['order']['documents'] = [['id' => 23, 'name' => 'artwork.pdf', 'size' => 1024]];
        $body = json_encode($latest, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)->assertOk();
        $older = $latest;
        $older['order']['revision'] = 399;
        $older['order']['documents'] = [];
        $older['order']['status'] = 'Draft';
        $body = json_encode($older, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)->assertOk();
        $payload = SupplierResource::where('supplier_company_id', $company->id)->first()->payload;
        $this->assertSame(400, $payload['revision']);
        $this->assertSame('Completed', $payload['order_status']);
        $this->assertSame(23, $payload['documents'][0]['id']);
        $revoked = ['event_id' => $latest['event_id'], 'supplier_id' => 101,
            'order' => ['reference' => $latest['order']['reference'], 'revoked' => true, 'revision' => 401]];
        $body = json_encode($revoked, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)->assertOk();
        $resource = SupplierResource::where('supplier_company_id', $company->id)->first();
        $this->assertSame('revoked', $resource->status);
        $this->assertArrayNotHasKey('documents', $resource->payload);
    }

    public function test_newer_signed_workflow_replaces_stage_and_grants_only_assigned_supplier_action(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        $company = SupplierCompany::create([
            'reference' => 'SUP101', 'name' => 'Workflow Supplier',
            'active' => true, 'flowtrack_supplier_id' => 101,
        ]);
        $data = $this->data(101);
        $data['order']['revision'] = 100;
        $data['order']['source_id'] = 454;
        $newOrder = [
            'id' => 1, 'sequence' => 1, 'name' => 'New Order', 'color' => '#3b82f6',
            'state' => 'active', 'state_label' => 'Current', 'dependency' => 'Starts when created',
            'owner' => 'Unassigned', 'progress' => 0, 'required_complete' => 0,
            'required_total' => 1, 'tasks' => [],
        ];
        $artwork = [
            'id' => 2, 'sequence' => 2, 'name' => 'Artwork', 'color' => '#8b5cf6',
            'state' => 'locked', 'state_label' => 'Upcoming', 'dependency' => 'After New Order',
            'owner' => 'Unassigned', 'progress' => 0, 'required_complete' => 0,
            'required_total' => 1, 'tasks' => [[
                'id' => 22, 'code' => '2.1', 'title' => 'Artwork approval',
                'status' => 'Not Started', 'mode' => 'locked', 'required' => true,
                'assignee' => 'Unassigned', 'assigned_to_supplier' => false,
                'due_date' => null, 'can_act' => false, 'action_key' => null,
            ]],
        ];
        $data['order']['workflow'] = ['current_phase_id' => 1, 'stages' => [$newOrder, $artwork]];
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)
            ->assertOk()->assertJsonPath('applied', true);

        $data['order']['revision'] = 200;
        $data['order']['workflow']['current_phase_id'] = 2;
        $data['order']['workflow']['stages'][0]['state'] = 'completed';
        $data['order']['workflow']['stages'][1]['state'] = 'active';
        $data['order']['workflow']['stages'][1]['tasks'][0]['mode'] = 'active';
        $data['order']['workflow']['stages'][1]['tasks'][0]['status'] = 'Ready';
        $data['order']['workflow']['stages'][1]['tasks'][0]['assignee'] = 'Your company';
        $data['order']['workflow']['stages'][1]['tasks'][0]['assigned_to_supplier'] = true;
        $data['order']['workflow']['stages'][1]['tasks'][0]['can_act'] = true;
        $data['order']['workflow']['stages'][1]['tasks'][0]['action_key'] = 'ART_CLIENT_ERP_DECISION';
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)
            ->assertOk()->assertJsonPath('applied', true);

        $saved = SupplierResource::where('supplier_company_id', $company->id)->firstOrFail();
        $this->assertSame(2, $saved->payload['workflow']['current_phase_id']);
        $this->assertSame('completed', $saved->payload['workflow']['stages'][0]['state']);
        $task = $saved->payload['workflow']['stages'][1]['tasks'][0];
        $this->assertTrue($task['assigned_to_supplier']);
        $this->assertTrue($task['can_act']);
        $this->assertSame('ART_CLIENT_ERP_DECISION', $task['action_key']);

        $data['order']['revision'] = 150;
        $data['order']['workflow']['current_phase_id'] = 1;
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)
            ->assertOk()->assertJsonPath('applied', false);
        $this->assertSame(2, $saved->fresh()->payload['workflow']['current_phase_id']);

        // Empty task lists are legitimate, but the field must still be present.
        $missingTasks = $data;
        $missingTasks['order']['revision'] = 250;
        unset($missingTasks['order']['workflow']['stages'][0]['tasks']);
        $body = json_encode($missingTasks, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)
            ->assertUnprocessable();
        $this->assertSame(200, $saved->fresh()->payload['revision']);
    }



    public function test_redo_metadata_is_supplier_scoped_validated_and_rendered_without_customer_financial_data(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        $company = SupplierCompany::create([
            'reference' => 'REDO101', 'name' => 'Redo Supplier', 'active' => true,
            'flowtrack_supplier_id' => 101,
        ]);
        $other = SupplierCompany::create([
            'reference' => 'REDO202', 'name' => 'Other Supplier', 'active' => true,
            'flowtrack_supplier_id' => 202,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id, 'name' => 'Redo User',
            'email' => 'redo-supplier@example.test', 'password' => 'LongValidPassword12', 'active' => true,
        ]);
        $outsider = SupplierUser::create([
            'supplier_company_id' => $other->id, 'name' => 'Other User',
            'email' => 'redo-other@example.test', 'password' => 'LongValidPassword12', 'active' => true,
        ]);
        $data = $this->data(101);
        $data['order']['reference'] = 'ORDER-2026-0099-R1';
        $data['order']['revision'] = 100;
        $data['order']['redo'] = [
            'original_reference' => 'ORDER-2026-0099',
            'scope' => 'production',
            'issue_category' => 'Stitching',
            'issue_description' => 'Replace damaged stitching. <script>alert(1)</script>',
            'affected_quantity' => 12,
            'redo_quantity' => 10,
            'supplier_adjustment' => [
                'type' => 'percent', 'value' => 12.5,
                'charge' => 250.75, 'freight' => 35.5, 'courier_freight' => 12.5,
                'total_recovery' => 286.25,
            ],
            // Untrusted keys are always dropped at the receiver.
            'customer_impact' => 12345,
            'supplier_adjustment_value' => 99,
            'internal_instructions' => 'PRIVATE REDO INSTRUCTIONS',
        ];
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)
            ->assertOk()->assertJsonPath('applied', true);

        $order = SupplierResource::query()->where('supplier_company_id', $company->id)->firstOrFail();
        $this->assertSame('ORDER-2026-0099', $order->payload['redo']['original_reference']);
        $this->assertSame(10, $order->payload['redo']['redo_quantity']);
        $this->assertSame('percent', $order->payload['redo']['supplier_adjustment']['type']);
        $this->assertSame(12.5, $order->payload['redo']['supplier_adjustment']['value']);
        $this->assertSame(250.75, $order->payload['redo']['supplier_adjustment']['charge']);
        $this->assertSame(35.5, $order->payload['redo']['supplier_adjustment']['freight']);
        $this->assertSame(12.5, $order->payload['redo']['supplier_adjustment']['courier_freight']);
        $this->assertSame(286.25, $order->payload['redo']['supplier_adjustment']['total_recovery']);
        $this->assertArrayNotHasKey('customer_impact', $order->payload['redo']);
        $this->assertArrayNotHasKey('supplier_adjustment_value', $order->payload['redo']);
        $this->assertArrayNotHasKey('internal_instructions', $order->payload['redo']);

        $this->actingAs($outsider)->get('/orders/'.$order->id)->assertNotFound();
        $this->actingAs($user)->get('/orders/'.$order->id)
            ->assertOk()->assertSee('Redo details')->assertSee('Original order')
            ->assertSee('Production only')->assertSee('Replace damaged stitching')
            ->assertDontSee('<script>', false)->assertDontSee('PRIVATE REDO INSTRUCTIONS')
            ->assertDontSee('12345')
            ->assertSee('Supplier financial impact')->assertSee('Supplier · 12.5%')
            ->assertSee('$250.75')->assertSee('$35.50')->assertSee('$12.50')->assertSee('$286.25')
            ->assertDontSee('Customer ·')->assertDontSee('Affected value');
        $this->actingAs($user)->get('/?view=orders')->assertOk()->assertSee('Redo order');

        // Older signed snapshots cannot erase a newer redo summary.
        $stale = $data;
        $stale['order']['revision'] = 99;
        unset($stale['order']['redo']);
        $body = json_encode($stale, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)
            ->assertOk()->assertJsonPath('applied', false);
        $this->assertArrayHasKey('redo', $order->fresh()->payload);
        $this->assertSame(286.25, $order->fresh()->payload['redo']['supplier_adjustment']['total_recovery']);
    }

    public function test_incomplete_or_financial_only_redo_projection_is_rejected(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        SupplierCompany::create([
            'reference' => 'REDO101', 'name' => 'Redo Supplier', 'active' => true,
            'flowtrack_supplier_id' => 101,
        ]);
        $data = $this->data(101);
        $data['order']['redo'] = [
            'original_reference' => 'ORDER-2026-0099',
            'scope' => 'discount',
            'issue_category' => 'Wrong color',
            'affected_quantity' => 5,
            // Missing redo quantity, and discount isn't a production redo.
        ];
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)
            ->assertUnprocessable();
        $this->assertSame(0, SupplierResource::query()->count());
    }

    public function test_redo_supplier_adjustment_in_pieces_is_displayed_and_invalid_adjustment_is_rejected(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        $company = SupplierCompany::create([
            'reference' => 'PCS101', 'name' => 'Piece Supplier', 'active' => true,
            'flowtrack_supplier_id' => 101,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id, 'name' => 'Piece User',
            'email' => 'redo-pieces@example.test', 'password' => 'LongValidPassword12', 'active' => true,
        ]);
        $data = $this->data(101);
        $data['order']['redo'] = [
            'original_reference' => 'ORDER-2026-0098',
            'scope' => 'artwork', 'issue_category' => 'Wrong size',
            'issue_description' => 'Redo two pieces',
            'affected_quantity' => 12, 'redo_quantity' => 10,
            'supplier_adjustment' => [
                'type' => 'pcs', 'value' => 2,
                'charge' => 30.25, 'freight' => 0, 'total_recovery' => 30.25,
            ],
        ];
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)
            ->assertOk()->assertJsonPath('applied', true);

        $resource = SupplierResource::query()->where('supplier_company_id', $company->id)->firstOrFail();
        $this->actingAs($user)->get('/orders/'.$resource->id)
            ->assertOk()->assertSee('2 pcs')->assertSee('$30.25')
            ->assertSee('Courier company freight deduction')->assertSee('Not applicable');

        $invalid = $data;
        $invalid['order']['revision'] = 200;
        unset($invalid['order']['redo']['supplier_adjustment']['charge']);
        $body = json_encode($invalid, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)
            ->assertUnprocessable();
        $this->assertSame(30.25, $resource->fresh()->payload['redo']['supplier_adjustment']['total_recovery']);

        $invalid['order']['redo']['supplier_adjustment']['charge'] = -1;
        $body = json_encode($invalid, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)
            ->assertUnprocessable();

        $zero = $data;
        $zero['order']['revision'] = 300;
        $zero['order']['redo']['supplier_adjustment'] = [
            'type' => 'percent', 'value' => 40,
            'charge' => 0.0, 'freight' => 0.0, 'courier_freight' => 0.0, 'total_recovery' => 0.0,
        ];
        $body = json_encode($zero, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)
            ->assertOk()->assertJsonPath('applied', true);
        $this->actingAs($user)->get('/orders/'.$resource->id)
            ->assertOk()->assertSee('Supplier · 40%')->assertSee('$0.00')
            ->assertSee('Not applicable')->assertSee('Total supplier recovery')
            ->assertDontSee('Customer ·');
        // JSON storage may decode 0.0 as integer 0; both represent valid zero money.
        $savedAdjustment = $resource->fresh()->payload['redo']['supplier_adjustment'];
        $this->assertArrayHasKey('charge', $savedAdjustment);
        $this->assertSame(0.0, (float) $savedAdjustment['charge']);
        $this->assertSame(0.0, (float) $savedAdjustment['total_recovery']);

        $invalidCourier = $zero;
        $invalidCourier['order']['revision'] = 400;
        $invalidCourier['order']['redo']['supplier_adjustment']['courier_freight'] = -1;
        $body = json_encode($invalidCourier, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)
            ->assertUnprocessable();
        $this->assertSame(300, $resource->fresh()->payload['revision']);
    }

    public function test_normal_order_does_not_show_redo_ui_or_require_redo_metadata(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        $company = SupplierCompany::create([
            'reference' => 'NORMAL101', 'name' => 'Normal Supplier', 'active' => true,
            'flowtrack_supplier_id' => 101,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id, 'name' => 'Normal User',
            'email' => 'normal-supplier@example.test', 'password' => 'LongValidPassword12', 'active' => true,
        ]);
        $data = $this->data(101);
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/orders', [], [], [], $this->headers($body), $body)
            ->assertOk();
        $order = SupplierResource::query()->where('supplier_company_id', $company->id)->firstOrFail();
        $this->assertArrayNotHasKey('redo', $order->payload);
        $this->actingAs($user)->get('/orders/'.$order->id)
            ->assertOk()->assertDontSee('Redo details')->assertDontSee('Redo order');
        $this->actingAs($user)->get('/?view=orders')->assertOk()->assertDontSee('Redo order');
    }
}
