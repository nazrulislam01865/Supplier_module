<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Supplier QC actions use the signed FlowTracker workflow contract; no extra local QC state. */
class SupplierQcWorkflowCycleTest extends TestCase
{
    use RefreshDatabase;

    private function qcOrder(string $key, string $status = 'Ready'): array
    {
        $company = SupplierCompany::create([
            'reference' => 'QC-SUPPLIER', 'name' => 'Quality Supplier', 'active' => true,
            'flowtrack_supplier_id' => 91,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id, 'name' => 'Quality Inspector',
            'email' => 'qc@example.test', 'password' => 'LongValidPassword123!', 'active' => true,
        ]);
        $order = SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'order',
            'external_reference' => 'ORD-QC-201', 'status' => 'new',
            'payload' => [
                'source_id' => 201, 'title' => 'QC Order',
                'items' => [['product_name' => 'Jersey', 'quantity' => 40]], 'documents' => [],
                'workflow' => ['current_phase_id' => 4, 'stages' => [[
                    'id' => 4, 'sequence' => 4, 'name' => 'Quality Control', 'color' => '#008a7a',
                    'state' => 'active', 'state_label' => 'Current', 'dependency' => '',
                    'owner' => 'Your company', 'progress' => 0,
                    'required_total' => 1, 'required_complete' => 0,
                    'tasks' => [[
                        'id' => 407, 'code' => '4.1', 'title' => 'QC task',
                        'status' => $status, 'mode' => 'active', 'required' => true,
                        'assignee' => 'Your company', 'assigned_to_supplier' => true,
                        'due_date' => null, 'can_act' => true, 'action_key' => $key,
                    ]],
                ]]],
            ],
        ]);
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.buyer_url', 'http://127.0.0.1:8000');
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');

        return [$user, $order];
    }

    private function quantities(): array
    {
        return ['qty_received' => 40, 'qty_inspected' => 4,
            'qty_accepted' => 3, 'qty_rejected' => 1];
    }

    public function test_supplier_qc_form_matches_flowtracker_defaults_and_separate_comments(): void
    {
        [$user, $order] = $this->qcOrder('QC_CHECK');
        $this->actingAs($user)->get(route('supplier.orders.show', $order->id))
            ->assertOk()->assertSee('Quantity received')
            ->assertSee('name="qty_received" type="number" min="1" required value="40"', false)
            ->assertSee('name="qty_inspected" type="number" min="1" required value="4"', false)
            ->assertSee('name="qty_accepted" type="number" min="0" required value="4"', false)
            ->assertSee('name="qc_comments"', false)
            ->assertSee('data-supplier-qc-report-open', false)
            ->assertSee('Product Quality Check')
            ->assertSee('Report QC Issue')
            ->assertDontSee('Attach task evidence (optional)');
    }

    public function test_supplier_qc_pass_sends_correct_signed_payload_and_records_single_activity(): void
    {
        [$user, $order] = $this->qcOrder('QC_CHECK');
        Http::fake(['http://127.0.0.1:8000/*' => Http::response([
            'received' => true, 'task_status' => 'Completed',
        ], 200)]);
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]),
            $this->quantities() + ['decision' => 'pass', 'qc_comments' => 'Inspected seams and labels.']
        )->assertRedirect(route('supplier.orders.show', $order->id))
            ->assertSessionHas('success', fn ($message): bool => str_contains($message, 'QC passed'));
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $body = (string) $request->body();
            $data = json_decode($body, true);
            return $data['decision'] === 'pass'
                && $data['payload']['qty_received'] === 40
                && $data['payload']['qty_inspected'] === 4
                && $data['payload']['qty_accepted'] === 3
                && $data['payload']['qty_rejected'] === 1
                && $data['payload']['qc_comments'] === 'Inspected seams and labels.'
                && ! $request->hasHeader('X-Supplier-Digests')
                && hash_equals(hash_hmac('sha256',
                    $request->header('X-Supplier-Timestamp')[0]."\n"
                    .$request->header('X-Supplier-Event')[0]."\n".$body,
                    config('flowtrack_bridge.secret')),
                    $request->header('X-Supplier-Signature')[0]);
        });
        $this->assertDatabaseHas('supplier_order_activities', [
            'supplier_resource_id' => $order->id, 'task_id' => 407,
            'decision' => 'pass', 'task_status' => 'Completed',
        ]);
    }

    public function test_browser_number_inputs_are_sent_as_integer_qc_counts(): void
    {
        [$user, $order] = $this->qcOrder('QC_CHECK');
        Http::fake(['http://127.0.0.1:8000/*' => Http::response([
            'received' => true, 'task_status' => 'Completed',
        ], 200)]);
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]), [
            'decision' => 'pass', 'qty_received' => '40', 'qty_inspected' => '4',
            'qty_accepted' => '4', 'qty_rejected' => '0',
        ])->assertRedirect(route('supplier.orders.show', $order->id));
        Http::assertSent(fn ($request): bool =>
            data_get(json_decode($request->body(), true), 'payload.qty_received') === 40
            && data_get(json_decode($request->body(), true), 'payload.qty_inspected') === 4
            && data_get(json_decode($request->body(), true), 'payload.qty_rejected') === 0);
    }

    public function test_qc_issue_requires_description_then_sends_category_quantities_and_notes(): void
    {
        [$user, $order] = $this->qcOrder('QC_CHECK');
        Http::fake(['http://127.0.0.1:8000/*' => Http::response([
            'received' => true, 'task_status' => 'Waiting for QC Issue Resolution',
        ], 200)]);
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]),
            $this->quantities() + ['decision' => 'issue', 'comment' => '  ']
        )->assertSessionHasErrors('comment');
        Http::assertNothingSent();
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]),
            $this->quantities() + ['decision' => 'issue', 'comment' => 'Three labels misprinted.',
                'qc_comments' => 'Measured every sample.', 'issue_category' => 'Print color mismatch']
        )->assertRedirect(route('supplier.orders.show', $order->id))
            ->assertSessionHas('success', fn ($message): bool => str_contains($message, 'Resolve the QC issue'));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool =>
            data_get(json_decode($request->body(), true), 'decision') === 'issue'
            && data_get(json_decode($request->body(), true), 'comment') === 'Three labels misprinted.'
            && data_get(json_decode($request->body(), true), 'payload.issue_category') === 'Print color mismatch'
            && data_get(json_decode($request->body(), true), 'payload.qc_comments') === 'Measured every sample.');
    }

    public function test_qc_invalid_quantities_or_files_never_reach_flowtracker(): void
    {
        [$user, $order] = $this->qcOrder('QC_CHECK');
        Http::fake();
        foreach ([
            ['qty_received' => 0, 'qty_inspected' => 1, 'qty_accepted' => 0, 'qty_rejected' => 0],
            ['qty_received' => 4, 'qty_inspected' => 5, 'qty_accepted' => 4, 'qty_rejected' => 0],
            ['qty_received' => 4, 'qty_inspected' => 4, 'qty_accepted' => 4, 'qty_rejected' => 1],
        ] as $quantities) {
            $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]),
                $quantities + ['decision' => 'pass'])->assertSessionHasErrors();
        }
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]),
            $this->quantities() + ['decision' => 'pass',
                'files' => [UploadedFile::fake()->create('qc.pdf', 1, 'application/pdf')]]
        )->assertSessionHasErrors('files');
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]),
            $this->quantities() + ['decision' => 'resolve']
        )->assertSessionHasErrors('decision');
        Http::assertNothingSent();
        $this->assertDatabaseCount('supplier_order_activities', 0);
    }

    public function test_qc_resolution_and_shipment_approval_use_existing_flowtracker_tasks(): void
    {
        [$user, $order] = $this->qcOrder('QC_ISSUE', 'QC Issue Reported');
        Http::fake(['http://127.0.0.1:8000/*' => Http::response([
            'received' => true, 'task_status' => 'Completed',
        ], 200)]);
        $this->actingAs($user)->get(route('supplier.orders.show', $order->id))
            ->assertOk()->assertSee('Resolve QC Issue')->assertSee('Resolution');
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]),
            ['decision' => 'resolve', 'comment' => '']
        )->assertSessionHasErrors('comment');
        Http::assertNothingSent();
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]),
            ['decision' => 'resolve', 'comment' => 'Reworked labels and rechecked goods.']
        )->assertRedirect(route('supplier.orders.show', $order->id));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool =>
            data_get(json_decode($request->body(), true), 'expected_status') === 'QC Issue Reported'
            && data_get(json_decode($request->body(), true), 'decision') === 'resolve');
        $this->assertDatabaseHas('supplier_order_activities', ['task_id' => 407, 'decision' => 'resolve']);

        $payload = $order->payload;
        $payload['workflow']['stages'][0]['tasks'][0]['action_key'] = 'QC_APPROVE_SHIPMENT';
        $payload['workflow']['stages'][0]['tasks'][0]['status'] = 'Ready';
        $order->update(['payload' => $payload]);
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]),
            ['decision' => 'confirm']
        )->assertRedirect(route('supplier.orders.show', $order->id));
        Http::assertSentCount(2);
        Http::assertSent(fn ($request): bool =>
            data_get(json_decode($request->body(), true), 'decision') === 'confirm');
    }

    public function test_issue_reported_in_erp_becomes_resolvable_when_signed_snapshot_arrives(): void
    {
        [$user, $order] = $this->qcOrder('QC_ISSUE', 'Not Started');
        $payload = $order->payload;
        $task = &$payload['workflow']['stages'][0]['tasks'][0];
        $task['mode'] = 'locked';
        $task['can_act'] = false;
        $task['action_key'] = null;
        $order->update(['payload' => $payload]);

        $this->actingAs($user)->get(route('supplier.orders.show', $order->id))
            ->assertOk()->assertSee('Waiting for previous task')
            ->assertDontSee('data-supplier-modal-open="supplier-flow-dialog-407"', false);
        Http::fake();
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]), [
            'decision' => 'resolve', 'comment' => 'Correction complete.',
        ])->assertForbidden();
        Http::assertNothingSent();

        // The existing signed order-receipt path replaces this snapshot after
        // FlowTracker publishes the canonical QC issue transition.
        $task['mode'] = 'active';
        $task['status'] = 'QC Issue Reported';
        $task['can_act'] = true;
        $task['action_key'] = 'QC_ISSUE';
        $order->update(['payload' => $payload]);
        $this->actingAs($user)->get(route('supplier.orders.show', $order->id))
            ->assertOk()->assertSee('Issue Resolved')
            ->assertSee('data-supplier-modal-open="supplier-flow-dialog-407"', false);
        Http::fake(['http://127.0.0.1:8000/*' => Http::response([
            'received' => true, 'task_status' => 'Completed',
        ], 200)]);
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]), [
            'decision' => 'resolve', 'comment' => 'Correction complete.',
        ])->assertRedirect(route('supplier.orders.show', $order->id));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool =>
            data_get(json_decode((string) $request->body(), true), 'expected_status') === 'QC Issue Reported'
            && data_get(json_decode((string) $request->body(), true), 'decision') === 'resolve');
    }

    public function test_rejected_qc_action_does_not_persist_local_success_or_activity(): void
    {
        [$user, $order] = $this->qcOrder('QC_CHECK');
        Http::fake(['http://127.0.0.1:8000/*' => Http::response(['message' => 'Stale task'], 409)]);
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]),
            $this->quantities() + ['decision' => 'pass']
        )->assertSessionHasErrors('workflow')->assertSessionMissing('success');
        $this->assertDatabaseCount('supplier_order_activities', 0);
        Http::assertSentCount(1);
    }
    public function test_qc_document_controls_are_supplier_scoped_and_appear_even_while_check_is_waiting(): void
    {
        [$user, $order] = $this->qcOrder('QC_CHECK', 'Waiting for QC Issue Resolution');
        $payload = $order->payload;
        $task = &$payload['workflow']['stages'][0]['tasks'][0];
        $task['can_act'] = false;
        $task['mode'] = 'locked';
        $task['action_key'] = null;
        $task['document_action_key'] = 'QC_CHECK';
        $task['can_attach'] = true;
        $task['document_files'] = [['id' => 13, 'name' => 'quality-report.pdf', 'size' => 1024]];
        $order->update(['payload' => $payload]);
        $this->actingAs($user)->get(route('supplier.orders.show', $order->id))
            ->assertOk()->assertSee('QC Document')
            ->assertSee('quality-report.pdf')
            ->assertSee('name="decision" value="attach"', false)
            ->assertSee('Upload QC Document');
    }

    public function test_attaching_qc_documents_is_signed_and_does_not_fake_qc_completion(): void
    {
        [$user, $order] = $this->qcOrder('QC_ISSUE', 'QC Issue Reported');
        $payload = $order->payload;
        $payload['workflow']['stages'][0]['tasks'][0]['document_action_key'] = 'QC_ISSUE';
        $payload['workflow']['stages'][0]['tasks'][0]['can_attach'] = true;
        $order->update(['payload' => $payload]);
        Http::fake(['http://127.0.0.1:8000/*' => Http::response([
            'received' => true, 'task_status' => 'QC Issue Reported',
        ], 200)]);
        $file = UploadedFile::fake()->create('inspection.pdf', 3, 'application/pdf');
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]), [
            'decision' => 'attach', 'files' => [$file],
        ])->assertRedirect(route('supplier.orders.show', $order->id))
          ->assertSessionHas('success', fn ($message): bool => str_contains($message, 'QC document saved'));
        Http::assertSentCount(1);
        Http::assertSent(function ($sent): bool {
            $data = (string) ($sent->data()['data'] ?? '');
            $decoded = json_decode($data, true);
            return $decoded['decision'] === 'attach'
                && $decoded['expected_status'] === 'QC Issue Reported'
                && $sent->hasHeader('X-Supplier-Digests')
                && $sent->hasHeader('X-Supplier-Signature');
        });
        $this->assertDatabaseHas('supplier_order_activities', [
            'supplier_resource_id' => $order->id, 'task_id' => 407,
            'decision' => 'attach', 'task_status' => 'QC Issue Reported',
        ]);
    }

    public function test_qc_document_is_not_accepted_without_current_supplier_evidence_permission(): void
    {
        [$user, $order] = $this->qcOrder('QC_CHECK');
        Http::fake();
        $this->actingAs($user)->post(route('supplier.orders.workflow.action', [$order->id, 407]), [
            'decision' => 'attach', 'files' => [UploadedFile::fake()->create('inspection.pdf', 3)],
        ])->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_qc_document_download_requires_file_in_assigned_task_manifest(): void
    {
        [$user, $order] = $this->qcOrder('QC_CHECK');
        Http::fake();
        $this->actingAs($user)->get(route('supplier.orders.qc-document', [$order->id, 33]))
            ->assertNotFound();
        Http::assertNothingSent();
    }

}
