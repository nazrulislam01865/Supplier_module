<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SupplierInquiryTaskDocumentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_completed_task_file_can_be_viewed_and_downloaded_only_by_its_supplier(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        config()->set('flowtrack_bridge.buyer_url', 'https://buyer.example.test');
        $company = SupplierCompany::create(['reference' => 'SUP1', 'name' => 'Supplier One',
            'active' => true, 'flowtrack_supplier_id' => 123]);
        $other = SupplierCompany::create(['reference' => 'SUP2', 'name' => 'Supplier Two',
            'active' => true, 'flowtrack_supplier_id' => 456]);
        $user = SupplierUser::create(['supplier_company_id' => $company->id,
            'name' => 'Staff', 'email' => 'one@example.test', 'password' => 'password', 'active' => true]);
        $outsider = SupplierUser::create(['supplier_company_id' => $other->id,
            'name' => 'Other', 'email' => 'two@example.test', 'password' => 'password', 'active' => true]);
        $content = '%PDF-test';
        $rfq = SupplierResource::create(['supplier_company_id' => $company->id,
            'type' => 'rfq', 'external_reference' => 'RFQ-000087', 'status' => 'submitted',
            'payload' => ['invitation_id' => 87, 'supplier_task' => [
                'id' => 900, 'title' => 'Contact Supplier for Costing', 'status' => 'Completed',
                'completed' => true, 'uploaded_files' => [[
                    'id' => 901, 'name' => 'costing.pdf', 'mime_type' => 'application/pdf',
                    'size' => strlen($content), 'version' => '1792043000000000',
                ]],
            ]],
        ]);
        $url = '/api/v1/rfqs/'.$rfq->id.'/supplier-task/documents/901';
        Http::fake(['buyer.example.test/*' => Http::response($content)]);

        $this->actingAs($outsider)->get($url)->assertNotFound();
        $this->actingAs($user)->get('/api/v1/rfqs/'.$rfq->id.'/supplier-task/documents/902')->assertNotFound();
        Http::assertNothingSent();

        $this->actingAs($user)->get($url.'?view=1')->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertSee($content, false);
        $this->actingAs($user)->get($url)->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream')
            ->assertSee($content, false);
        Http::assertSentCount(2);
        Http::assertSent(static function ($request): bool {
            $time = $request->header('X-FlowTrack-Timestamp')[0] ?? '';
            $sig = $request->header('X-FlowTrack-Signature')[0] ?? '';
            $expected = hash_hmac('sha256', $time."\n123\n87\n901\n1792043000000000",
                'test-bridge-secret-at-least-32-characters');
            return $request->url() === 'https://buyer.example.test/api/supplier-bridge/rfq-task-documents/87/901'
                && hash_equals($expected, $sig);
        });
    }
}
