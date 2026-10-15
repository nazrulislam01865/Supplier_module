<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupplierWorkflowActionSyncTest extends TestCase
{
    use RefreshDatabase;

    private function assignedOrder(string $key = 'PROD_START', bool $assigned = true): array
    {
        $company = SupplierCompany::create([
            'reference' => 'ACME-WORKFLOW', 'name' => 'Acme Workflow', 'active' => true,
            'flowtrack_supplier_id' => 91,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id, 'name' => 'Supplier operator',
            'email' => 'workflows@example.test', 'password' => 'LongValidPassword123!', 'active' => true,
        ]);
        $order = SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'order',
            'external_reference' => 'ORD-SUP-201', 'status' => 'new',
            'payload' => [
                'source_id' => 201, 'title' => 'Test order', 'items' => [], 'documents' => [],
                'workflow' => ['current_phase_id' => 3, 'stages' => [[
                    'id' => 3, 'sequence' => 3, 'name' => 'Production', 'color' => '#008a7a',
                    'state' => 'active', 'state_label' => 'Current', 'dependency' => '',
                    'owner' => 'Your company', 'progress' => 0,
                    'required_total' => 1, 'required_complete' => 0,
                    'tasks' => [[
                        'id' => 407, 'code' => '3.1', 'title' => 'Supplier task',
                        'status' => 'Ready', 'mode' => 'active', 'required' => true,
                        'assignee' => 'Your company', 'assigned_to_supplier' => $assigned,
                        'due_date' => null, 'can_act' => true, 'action_key' => $key,
                    ]],
                ]]],
            ],
        ]);
        return [$user, $order];
    }

    private function enableBridge(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.buyer_url', 'http://127.0.0.1:8000');
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
    }

    public function test_assigned_active_task_shows_its_action_in_order_details(): void
    {
        [$user, $order] = $this->assignedOrder('PROD_START');
        $this->actingAs($user)->get(route('supplier.orders.show', $order->id))
            ->assertOk()->assertSee('Start production');
    }

    public function test_assigned_locked_task_explains_why_there_is_no_action(): void
    {
        [$user, $order] = $this->assignedOrder('PROD_START');
        $payload = $order->payload;
        $payload['workflow']['stages'][0]['tasks'][0]['mode'] = 'locked';
        $payload['workflow']['stages'][0]['tasks'][0]['can_act'] = false;
        $order->update(['payload' => $payload]);
        $this->actingAs($user)->get(route('supplier.orders.show', $order->id))
            ->assertOk()->assertSee('Waiting for previous task');
    }

    public function test_only_explicitly_assigned_supplier_can_trigger_signed_workflow_action(): void
    {
        [$user, $order] = $this->assignedOrder();
        $this->enableBridge();
        Http::fake(['http://127.0.0.1:8000/*' => Http::response(['received' => true], 200)]);
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]), [
            'decision' => 'confirm',
        ])->assertRedirect(route('supplier.orders.show', $order->id));
        $this->assertDatabaseHas('supplier_order_activities', [
            'supplier_company_id' => $user->supplier_company_id,
            'supplier_resource_id' => $order->id,
            'supplier_user_id' => $user->id,
            'task_id' => 407,
            'stage_name' => 'Production',
            'task_title' => 'Supplier task',
            'actor_name' => 'Supplier operator',
            'decision' => 'confirm',
            'task_status' => 'Accepted',
        ]);
        Http::assertSent(function ($request): bool {
            $body = $request->body();
            $data = json_decode((string) $body, true);
            return ($data['supplier_id'] ?? null) === 91
                && ($data['order_id'] ?? null) === 201
                && ($data['task_id'] ?? null) === 407
                && ($data['expected_status'] ?? null) === 'Ready'
                && hash_equals(
                    hash_hmac('sha256', $request->header('X-Supplier-Timestamp')[0]."\n"
                        .$request->header('X-Supplier-Event')[0]."\n".$body,
                        config('flowtrack_bridge.secret')),
                    $request->header('X-Supplier-Signature')[0]
                );
        });
    }

    public function test_unassigned_supplier_cannot_send_an_action_to_flowtracker(): void
    {
        [$user, $order] = $this->assignedOrder('PROD_START', false);
        $this->enableBridge();
        Http::fake();
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]), [
            'decision' => 'confirm',
        ])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_client_erp_upload_is_recorded_as_waiting_for_client_not_completed(): void
    {
        [$user, $order] = $this->assignedOrder('ART_CLIENT_ERP_DECISION');
        $this->enableBridge();
        Http::fake(['http://127.0.0.1:8000/*' => Http::response([
            'received' => true, 'task_status' => 'Waiting for Client',
        ], 200)]);
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]), [
            'decision' => 'erp_uploaded', 'erp_reference' => 'ERP-ABC-001',
        ])->assertRedirect(route('supplier.orders.show', $order->id))
            ->assertSessionHas('success', function ($message): bool {
                return str_contains($message, 'waiting for the client decision');
            });
        Http::assertSent(fn ($request): bool =>
            data_get(json_decode($request->body(), true), 'decision') === 'erp_uploaded'
            && data_get(json_decode($request->body(), true), 'payload.erp_reference') === 'ERP-ABC-001');
    }

    public function test_supplier_cannot_skip_erp_upload_and_approve_directly(): void
    {
        [$user, $order] = $this->assignedOrder('ART_CLIENT_ERP_DECISION');
        $this->enableBridge();
        Http::fake();
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]), [
            'decision' => 'confirm',
        ])->assertSessionHasErrors('workflow');
        Http::assertNothingSent();
    }

    public function test_client_approval_after_waiting_is_signed_and_sent_to_flowtracker(): void
    {
        [$user, $order] = $this->assignedOrder('ART_CLIENT_ERP_DECISION');
        $payload = $order->payload;
        $payload['workflow']['stages'][0]['tasks'][0]['status'] = 'Waiting for Client';
        $order->update(['payload' => $payload]);
        $this->enableBridge();
        Http::fake(['http://127.0.0.1:8000/*' => Http::response([
            'received' => true, 'task_status' => 'Completed',
        ], 200)]);
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]), [
            'decision' => 'confirm',
        ])->assertRedirect(route('supplier.orders.show', $order->id));
        Http::assertSent(fn ($request): bool =>
            data_get(json_decode($request->body(), true), 'expected_status') === 'Waiting for Client'
            && data_get(json_decode($request->body(), true), 'decision') === 'confirm');
    }

    public function test_rejected_flowtracker_action_is_not_reported_as_saved(): void
    {
        [$user, $order] = $this->assignedOrder('ART_CLIENT_ERP_DECISION');
        $this->enableBridge();
        Http::fake(['http://127.0.0.1:8000/*' => Http::response(['message' => 'Conflict'], 409)]);
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]), [
            'decision' => 'erp_uploaded', '_supplier_workflow_task' => 407,
        ])->assertSessionHasErrors('workflow')
          ->assertSessionMissing('success');
        $this->assertDatabaseCount('supplier_order_activities', 0);
    }

    public function test_supplier_file_is_sent_as_signed_checksum_verified_multipart_evidence(): void
    {
        [$user, $order] = $this->assignedOrder('NEW_UPLOAD_PO');
        $this->enableBridge();
        Http::fake(['http://127.0.0.1:8000/*' => Http::response(['received' => true], 200)]);
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]), [
            'decision' => 'upload', 'files' => [UploadedFile::fake()->create('purchase-order.pdf', 2, 'application/pdf')],
        ])->assertRedirect(route('supplier.orders.show', $order->id));
        Http::assertSent(fn ($request) => $request->hasHeader('X-Supplier-Digests')
            && $request->hasHeader('X-Supplier-Signature')
            && str_contains((string) $request->header('Content-Type')[0], 'multipart/form-data'));
    }
}
