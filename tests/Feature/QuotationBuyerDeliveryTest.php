<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierDocument;
use App\Models\SupplierQuotation;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use App\Services\FlowtrackQuotationDelivery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

final class QuotationBuyerDeliveryTest extends TestCase
{
    use RefreshDatabase;

    private function quotation(): SupplierQuotation
    {
        $company = SupplierCompany::create(['reference'=>'SUPPLIER-Q','name'=>'Quoted Supplier','active'=>true,'flowtrack_supplier_id'=>34785]);
        $user = SupplierUser::create(['supplier_company_id'=>$company->id,'name'=>'Submitter','email'=>'submit@example.test','password'=>'password123','active'=>true]);
        $resource = SupplierResource::create([
            'supplier_company_id'=>$company->id,'type'=>'rfq','external_reference'=>'RFQ-000007',
            'status'=>'submitted','payload'=>['invitation_id'=>7,'items'=>[['id'=>50,'product_name'=>'Bag','quantity'=>10]]],
        ]);
        $quote = SupplierQuotation::create([
            'supplier_company_id'=>$company->id,'supplier_resource_id'=>$resource->id,'supplier_user_id'=>$user->id,
            'status'=>'submitted','submission_uuid'=>(string)Str::uuid(),'sync_status'=>'pending',
            'data'=>['contact'=>'Submitter','email'=>'submit@example.test','phone'=>'1234567','currency'=>'USD','unit_price'=>'10.00',
                'moq'=>1,'tooling'=>0,'sample'=>0,'discount'=>0,'tax'=>'Included','production_lead'=>10,
                'sample_lead'=>5,'incoterm'=>'FOB','shipping_port'=>'Dhaka','delivery_date'=>'2026-11-15',
                'validity'=>30,'compliance'=>'Yes, fully compliant'],
            'total_minor_units'=>10000,'submitted_at'=>now(),
        ]);
        foreach (['Formal quotation'=>'quotation.pdf','Price breakdown'=>'breakdown.pdf'] as $type=>$name) {
            $path='supplier/'.$company->id.'/rfq/'.$resource->id.'/'.$name;
            Storage::disk('private')->put($path,'%PDF-1.4 test '.$name);
            SupplierDocument::create([
                'supplier_company_id'=>$company->id,'supplier_resource_id'=>$resource->id,
                'supplier_quotation_id'=>$quote->id,'uploaded_by'=>$user->id,'document_type'=>$type,
                'original_name'=>$name,'storage_path'=>$path,'mime_type'=>'application/pdf',
                'size'=>Storage::disk('private')->size($path),'scan_status'=>'clean',
            ]);
        }
        return $quote;
    }

    public function test_it_delivers_both_private_files_before_the_signed_quotation_and_does_not_duplicate(): void
    {
        Storage::fake('private');
        config()->set('flowtrack_bridge.enabled',true);
        config()->set('flowtrack_bridge.buyer_url','https://buyer.test');
        config()->set('flowtrack_bridge.secret','testing-secret-at-least-32-characters');
        $quote = $this->quotation();
        Http::fake(['https://buyer.test/*'=>Http::response(['received'=>true],200)]);
        $this->assertTrue(app(FlowtrackQuotationDelivery::class)->send($quote->id));
        $this->assertSame('delivered',$quote->fresh()->sync_status);
        Http::assertSentCount(3);
        Http::assertSent(fn ($request) => str_ends_with($request->url(),'/rfq-quotations') &&
            $request['invitation_id'] === 7 && $request['supplier_id'] === 34785 &&
            count($request['documents']) === 2 &&
            $request->hasHeader('X-Supplier-Signature'));
        $this->assertFalse(app(FlowtrackQuotationDelivery::class)->send($quote->id));
        Http::assertSentCount(3);
    }

    public function test_it_delivers_an_optional_document_manifest_without_requiring_legacy_file_types(): void
    {
        Storage::fake('private');
        config()->set('flowtrack_bridge.enabled',true);
        config()->set('flowtrack_bridge.buyer_url','https://buyer.test');
        config()->set('flowtrack_bridge.secret','testing-secret-at-least-32-characters');
        $quote = $this->quotation();
        // A supplier may submit an itemized price without uploading either legacy mandatory file.
        SupplierDocument::query()->where('supplier_resource_id',$quote->supplier_resource_id)->delete();
        Http::fake(['https://buyer.test/*'=>Http::response(['received'=>true],200)]);
        $this->assertTrue(app(FlowtrackQuotationDelivery::class)->send($quote->id));
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => str_ends_with($request->url(),'/rfq-quotations') &&
            $request['documents'] === []);
    }

    public function test_missing_buyer_url_has_an_actionable_configuration_error(): void
    {
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'testing-secret-at-least-32-characters');
        config()->set('flowtrack_bridge.buyer_url', '');
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('FLOWTRACK_BUYER_URL is missing');
        app(FlowtrackQuotationDelivery::class)->assertConfigured();
    }

    public function test_successful_http_without_receiver_ack_remains_pending(): void
    {
        Storage::fake('private');
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.buyer_url', 'https://buyer.test');
        config()->set('flowtrack_bridge.secret', 'testing-secret-at-least-32-characters');
        $quote = $this->quotation();
        SupplierDocument::query()->where('supplier_resource_id', $quote->supplier_resource_id)->delete();
        Http::fake(['https://buyer.test/*' => Http::response(['message' => 'Accepted'], 200)]);
        $this->assertFalse(app(FlowtrackQuotationDelivery::class)->send($quote->id));
        $this->assertSame('pending', $quote->fresh()->sync_status);
    }

    public function test_force_retry_allows_delayed_pending_delivery_without_resubmission(): void
    {
        Storage::fake('private');
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.buyer_url', 'https://buyer.test');
        config()->set('flowtrack_bridge.secret', 'testing-secret-at-least-32-characters');
        $quote = $this->quotation();
        SupplierDocument::query()->where('supplier_resource_id', $quote->supplier_resource_id)->delete();
        $quote->update(['sync_next_attempt_at' => now()->addHour(), 'sync_attempts' => 1]);
        Http::fake(['https://buyer.test/*' => Http::response(['received' => true], 200)]);
        $this->assertFalse(app(FlowtrackQuotationDelivery::class)->send($quote->id));
        $this->assertTrue(app(FlowtrackQuotationDelivery::class)->send($quote->id, true));
        $this->assertSame('delivered', $quote->fresh()->sync_status);
    }

    public function test_failed_buyer_delivery_is_retained_for_safe_retry(): void
    {
        Storage::fake('private');
        config()->set('flowtrack_bridge.enabled',true);
        config()->set('flowtrack_bridge.buyer_url','https://buyer.test');
        config()->set('flowtrack_bridge.secret','testing-secret-at-least-32-characters');
        $quote = $this->quotation();
        Http::fake(['https://buyer.test/*'=>Http::response(['message'=>'Unavailable'],503)]);
        $this->assertFalse(app(FlowtrackQuotationDelivery::class)->send($quote->id));
        $this->assertSame('pending',$quote->fresh()->sync_status);
        $this->assertSame(1,$quote->fresh()->sync_attempts);
        $this->assertNotNull($quote->fresh()->sync_next_attempt_at);
    }
}
