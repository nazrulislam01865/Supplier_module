<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class FlowtrackRfqApiTest extends TestCase
{
    use RefreshDatabase;

    private function envelope(): array
    {
        return [
            'event_id' => 'c09541f5-1bc7-4564-abd6-0d40ec325d21',
            'supplier_id' => 123,
            'rfq' => [
                'invitation_id' => 78, 'reference' => 'RFQ-000078',
                'inquiry_reference' => 'INQ-2026-008', 'subject' => 'New samples',
                'buyer_requirements' => 'Provide recycled material.',
                'request_message' => 'Please respond this week.', 'supplier_details' => '',
                'currency' => 'USD', 'delivery_date' => '2026-11-05',
                'due_at' => '2026-10-20T23:59:59+06:00',
                'items' => [
                    ['id' => 1, 'product_name' => 'Bag', 'product_code' => 'PRD-000001', 'category' => 'Bags', 'quantity' => 200.5, 'unit' => 'pcs', 'notes' => 'Use blue'],
                    ['id' => 2, 'product_name' => 'Bottle', 'product_code' => 'PRD-000002', 'category' => 'Drinkware', 'quantity' => 100, 'unit' => 'pcs', 'notes' => 'Steel'],
                ],
                'documents' => [['id' => 30, 'name' => 'specification.pdf', 'size' => 4096, 'mime_type' => 'application/pdf']],
            ],
        ];
    }

    private function headers(string $body): array
    {
        $stamp = (string) time();
        $event = 'c09541f5-1bc7-4564-abd6-0d40ec325d21';
        return [
            'CONTENT_TYPE' => 'application/json',
            'X-FlowTrack-Timestamp' => $stamp,
            'X-FlowTrack-Event' => $event,
            'X-FlowTrack-Signature' => hash_hmac('sha256', $stamp."\n".$event."\n".$body, 'test-bridge-secret-at-least-32-characters'),
        ];
    }

    public function test_signed_supplier_rfq_is_idempotent_and_preserves_draft(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        $one = SupplierCompany::create(['reference' => 'ONE', 'name' => 'One', 'active' => true, 'flowtrack_supplier_id' => 123]);
        $two = SupplierCompany::create(['reference' => 'TWO', 'name' => 'Two', 'active' => true, 'flowtrack_supplier_id' => 124]);
        $other = SupplierUser::create(['supplier_company_id' => $two->id, 'name' => 'Two User', 'email' => 'two@portal.test', 'password' => 'longpassword', 'active' => true]);
        $payload = $this->envelope();
        $payload['rfq']['customer_email'] = 'private@example.test';
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/rfqs', [], [], [], $this->headers($body), $body)->assertOk();
        $resource = SupplierResource::where('type', 'rfq')->firstOrFail();
        $this->assertSame($one->id, $resource->supplier_company_id);
        $this->assertSame(2, count($resource->payload['items']));
        $this->assertSame('PRD-000002', $resource->payload['items'][1]['product_code']);
        $this->assertArrayNotHasKey('customer_email', $resource->payload);
        $resource->update(['status' => 'draft']);
        $payload['rfq']['buyer_requirements'] = 'Updated specs.';
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/rfqs', [], [], [], $this->headers($body), $body)->assertOk();
        $this->assertSame(1, SupplierResource::where('type', 'rfq')->count());
        $this->assertSame('draft', $resource->fresh()->status);
        $this->assertSame('Updated specs.', $resource->fresh()->payload['buyer_requirements']);
        $this->actingAs($other)->getJson('/api/v1/rfqs/'.$resource->id.'/quotation')->assertNotFound();
    }

    public function test_signed_rfq_with_no_documents_is_accepted(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        $company = SupplierCompany::create([
            'reference' => 'ONE', 'name' => 'One', 'active' => true, 'flowtrack_supplier_id' => 123,
        ]);
        $payload = $this->envelope();
        $payload['rfq']['documents'] = [];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/rfqs', [], [], [], $this->headers($body), $body)->assertOk();
        $rfq = SupplierResource::query()->where('supplier_company_id', $company->id)->where('type', 'rfq')->firstOrFail();
        $this->assertSame([], $rfq->payload['documents']);
    }

    public function test_unsigned_cross_company_and_unmapped_requests_fail(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        $body = json_encode($this->envelope(), JSON_THROW_ON_ERROR);
        $this->call('POST', '/api/v1/integrations/flowtrack/rfqs', [], [], [], ['CONTENT_TYPE' => 'application/json'], $body)->assertUnauthorized();
        $this->call('POST', '/api/v1/integrations/flowtrack/rfqs', [], [], [], $this->headers($body), $body)->assertStatus(409);
        $this->assertSame(0, SupplierResource::count());
    }

    public function test_multi_product_quote_requires_separate_item_prices(): void
    {
        $company = SupplierCompany::create(['reference' => 'ONE', 'name' => 'One', 'active' => true, 'flowtrack_supplier_id' => 123]);
        $user = SupplierUser::create(['supplier_company_id' => $company->id, 'name' => 'One User', 'email' => 'one@portal.test', 'password' => 'longpassword', 'active' => true]);
        $rfq = SupplierResource::create(['supplier_company_id' => $company->id, 'type' => 'rfq',
            'external_reference' => 'RFQ-000078', 'status' => 'open', 'due_at' => now()->addWeek(),
            'payload' => ['quantity' => 300, 'items' => [
                ['id' => 1, 'product_name' => 'Bag', 'quantity' => 200],
                ['id' => 2, 'product_name' => 'Bottle', 'quantity' => 100],
            ]],
        ]);
        $data = [
            'contact' => 'One User', 'email' => 'one@portal.test', 'phone' => '123456789',
            'currency' => 'USD', 'unit_price' => '2.00', 'moq' => 1,
            'tooling' => '0', 'sample' => '0', 'discount' => '0', 'tax' => 'Excluded',
            'production_lead' => 15, 'sample_lead' => 5, 'incoterm' => 'FOB',
            'shipping_port' => 'Dhaka', 'delivery_date' => now()->addDays(10)->toDateString(),
            'validity' => 30, 'compliance' => 'Yes, fully compliant',
            'accuracy' => true, 'authority' => true,
        ];
        $this->actingAs($user)->postJson('/api/v1/rfqs/'.$rfq->id.'/submit', $data)
            ->assertUnprocessable()->assertJsonValidationErrors('item_prices');
        $data['item_prices'] = [['item_id' => 1, 'unit_price' => '2.00', 'moq' => 200]];
        $this->actingAs($user)->postJson('/api/v1/rfqs/'.$rfq->id.'/submit', $data)
            ->assertUnprocessable()->assertJsonValidationErrors('item_prices');
    }

    public function test_buyer_document_is_company_scoped_and_fetched_via_signed_server_request(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        config()->set('flowtrack_bridge.buyer_url', 'https://buyer.example.test');
        $one = SupplierCompany::create(['reference' => 'ONE', 'name' => 'One', 'active' => true, 'flowtrack_supplier_id' => 123]);
        $two = SupplierCompany::create(['reference' => 'TWO', 'name' => 'Two', 'active' => true, 'flowtrack_supplier_id' => 124]);
        $user = SupplierUser::create(['supplier_company_id' => $one->id, 'name' => 'One User', 'email' => 'one@portal.test', 'password' => 'longpassword', 'active' => true]);
        $other = SupplierUser::create(['supplier_company_id' => $two->id, 'name' => 'Two User', 'email' => 'two@portal.test', 'password' => 'longpassword', 'active' => true]);
        $resource = SupplierResource::create(['supplier_company_id' => $one->id, 'type' => 'rfq', 'status' => 'open',
            'external_reference' => 'RFQ-000078', 'due_at' => now()->addWeek(),
            'payload' => ['invitation_id' => 78, 'documents' => [['id' => 30, 'name' => 'specification.pdf']]]]);
        $this->actingAs($other)->get('/api/v1/rfqs/'.$resource->id.'/buyer-documents/30')->assertNotFound();
        Http::fake(['buyer.example.test/*' => Http::response('%PDF-1.7 example', 200)]);
        $this->actingAs($user)->get('/api/v1/rfqs/'.$resource->id.'/buyer-documents/30')->assertOk()
            ->assertHeader('Content-Type', 'application/octet-stream');
        Http::assertSent(fn ($request) => str_contains($request->url(), '/api/supplier-bridge/rfq-documents/78/30')
            && $request->hasHeader('X-FlowTrack-Signature'));
        $this->actingAs($user)->get('/api/v1/rfqs/'.$resource->id.'/buyer-documents/999')->assertNotFound();
    }
}
