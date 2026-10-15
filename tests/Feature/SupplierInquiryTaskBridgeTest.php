<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SupplierInquiryTaskBridgeTest extends TestCase
{
    use RefreshDatabase;

    public function test_supplier_completion_is_forwarded_directly_and_company_scoped(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        config()->set('flowtrack_bridge.buyer_url', 'https://buyer.example.test');
        $mine = SupplierCompany::create(['reference' => 'SUP1', 'name' => 'Supplier One',
            'active' => true, 'flowtrack_supplier_id' => 123]);
        $theirs = SupplierCompany::create(['reference' => 'SUP2', 'name' => 'Supplier Two',
            'active' => true, 'flowtrack_supplier_id' => 124]);
        $user = SupplierUser::create(['supplier_company_id' => $mine->id,
            'name' => 'Supplier Staff', 'email' => 'staff@example.test', 'password' => 'longpassword', 'active' => true]);
        $outsider = SupplierUser::create(['supplier_company_id' => $theirs->id,
            'name' => 'Other Staff', 'email' => 'other@example.test', 'password' => 'longpassword', 'active' => true]);
        $rfq = SupplierResource::create(['supplier_company_id' => $mine->id, 'type' => 'rfq',
            'external_reference' => 'RFQ-000087', 'status' => 'open', 'due_at' => now()->addWeek(),
            'payload' => ['invitation_id' => 87, 'supplier_task' => [
                'id' => 900, 'title' => 'Contact Supplier for Costing',
                'status' => 'Not Started', 'completed' => false, 'requires_submission' => false,
            ]],
        ]);
        Http::fake(['buyer.example.test/*' => Http::response([
            'received' => true, 'status' => 'Completed', 'completed' => true,
        ])]);
        $url = '/api/v1/rfqs/'.$rfq->id.'/supplier-task';
        $this->actingAs($outsider)->postJson($url, [
            'action' => 'complete', 'expected_status' => 'Not Started',
        ])->assertNotFound();
        Http::assertNothingSent();
        $this->actingAs($user)->postJson($url, [
            'action' => 'complete', 'expected_status' => 'Not Started',
        ])->assertOk()->assertJsonPath('task.completed', true);
        $this->assertTrue($rfq->fresh()->payload['supplier_task']['completed']);
        Http::assertSentCount(1);
        Http::assertSent(function ($request): bool {
            $payload = json_decode((string) $request->body(), true);
            return $request->url() === 'https://buyer.example.test/api/supplier-bridge/inquiry-task-actions'
                && $payload['supplier_id'] === 123
                && $payload['invitation_id'] === 87
                && $payload['task_id'] === 900
                && $payload['action'] === 'complete'
                && ! isset($payload['client_id']);
        });
    }

    public function test_completed_legacy_task_can_recover_a_missing_file_without_reopening(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        config()->set('flowtrack_bridge.buyer_url', 'https://buyer.example.test');
        $company = SupplierCompany::create(['reference' => 'RECOVER', 'name' => 'Supplier',
            'active' => true, 'flowtrack_supplier_id' => 123]);
        $user = SupplierUser::create(['supplier_company_id' => $company->id,
            'name' => 'Staff', 'email' => 'recover@example.test', 'password' => 'password', 'active' => true]);
        $rfq = SupplierResource::create(['supplier_company_id' => $company->id,
            'type' => 'rfq', 'external_reference' => 'RFQ-000087', 'status' => 'open',
            'payload' => ['invitation_id' => 87, 'supplier_task' => [
                'id' => 900, 'title' => 'Contact Supplier for Costing', 'status' => 'Completed',
                'completed' => true, 'uploaded_files' => ['older-costing.jpg'],
            ]],
        ]);
        Http::fake(['buyer.example.test/*' => Http::response([
            'received' => true, 'status' => 'Completed', 'completed' => true,
            'file' => ['id' => 901, 'name' => 'costing.jpg', 'mime_type' => 'image/jpeg',
                'size' => 2048, 'version' => ''],
        ])]);
        $this->actingAs($user)->post('/api/v1/rfqs/'.$rfq->id.'/supplier-task', [
            'action' => 'upload', 'expected_status' => 'Completed',
            'file' => \Illuminate\Http\UploadedFile::fake()->image('costing.jpg'),
        ])->assertOk()->assertJsonPath('task.completed', true)
            ->assertJsonPath('task.uploaded_files.0.id', 901);
        $saved = $rfq->fresh()->payload['supplier_task'];
        $this->assertTrue($saved['completed']);
        $this->assertSame(901, $saved['uploaded_files'][0]['id']);
        Http::assertSentCount(1);
        Http::assertSent(static function ($request): bool {
            return $request->url() === 'https://buyer.example.test/api/supplier-bridge/inquiry-task-actions';
        });

        Http::fake();
        $this->actingAs($user)->post('/api/v1/rfqs/'.$rfq->id.'/supplier-task', [
            'action' => 'upload', 'expected_status' => 'Completed',
            'file' => \Illuminate\Http\UploadedFile::fake()->image('costing.jpg'),
        ])->assertStatus(409);
        Http::assertNothingSent();
    }

    public function test_old_completed_projection_cannot_send_again(): void
    {
        $company = SupplierCompany::create(['reference' => 'TEST', 'name' => 'Supplier',
            'active' => true, 'flowtrack_supplier_id' => 201]);
        $user = SupplierUser::create(['supplier_company_id' => $company->id,
            'name' => 'Supplier Staff', 'email' => 'supplier@example.test', 'password' => 'longpassword', 'active' => true]);
        $rfq = SupplierResource::create(['supplier_company_id' => $company->id, 'type' => 'rfq',
            'external_reference' => 'RFQ-000101', 'status' => 'open',
            'payload' => ['invitation_id' => 101, 'supplier_task' => [
                'id' => 990, 'status' => 'Completed', 'completed' => true,
            ]],
        ]);
        Http::fake();
        $this->actingAs($user)->postJson('/api/v1/rfqs/'.$rfq->id.'/supplier-task', [
            'action' => 'complete', 'expected_status' => 'Completed',
        ])->assertStatus(409);
        Http::assertNothingSent();
    }
}
