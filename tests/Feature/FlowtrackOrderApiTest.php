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
            'X-FlowTrack-Timestamp' => $timestamp,
            'X-FlowTrack-Event' => $event,
            'X-FlowTrack-Signature' => hash_hmac('sha256', $timestamp."\n".$event."\n".$body, 'test-bridge-secret-at-least-32-characters'),
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
        $this->actingAs($user)->get('/orders/'.$saved->id)
            ->assertOk()->assertSee('£2.75')->assertSee('£550.00')
            ->assertSee('Sample approval')->assertSee('Skipped')
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
    }


}
