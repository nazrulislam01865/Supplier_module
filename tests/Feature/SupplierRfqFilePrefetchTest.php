<?php

namespace Tests\Feature;

use App\Jobs\WarmBuyerRfqFile;
use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use App\Services\BuyerRfqFileCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SupplierRfqFilePrefetchTest extends TestCase
{
    use RefreshDatabase;

    public function test_signed_document_is_prefetched_privately_and_not_exposed_to_other_suppliers(): void
    {
        Storage::fake('private');
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        config()->set('flowtrack_bridge.buyer_url', 'https://buyer.example.test');
        $one = SupplierCompany::create(['reference' => 'ONE', 'name' => 'One', 'active' => true, 'flowtrack_supplier_id' => 123]);
        $two = SupplierCompany::create(['reference' => 'TWO', 'name' => 'Two', 'active' => true, 'flowtrack_supplier_id' => 124]);
        $user = SupplierUser::create(['supplier_company_id' => $one->id, 'name' => 'One', 'email' => 'one@portal.test', 'password' => 'secret!', 'active' => true]);
        $stranger = SupplierUser::create(['supplier_company_id' => $two->id, 'name' => 'Two', 'email' => 'two@portal.test', 'password' => 'secret!', 'active' => true]);
        $bytes = '%PDF-1.7 sample';
        $doc = ['id' => 43, 'name' => 'buyer.pdf', 'size' => strlen($bytes), 'version' => '1791940000123456'];
        $rfq = SupplierResource::create(['supplier_company_id' => $one->id, 'type' => 'rfq', 'status' => 'open',
            'external_reference' => 'RFQ-000123', 'due_at' => now()->addDays(3),
            'payload' => ['invitation_id' => 123, 'documents' => [$doc]],
        ]);
        Http::fake(['buyer.example.test/*' => Http::response($bytes, 200)]);
        (new WarmBuyerRfqFile($rfq->id, 43, $doc['version']))->handle(app(BuyerRfqFileCache::class));
        $path = app(BuyerRfqFileCache::class)->cached($rfq, $doc);
        $this->assertNotNull($path);
        $this->assertStringStartsWith('buyer-rfq/'.$one->id.'/', $path);
        $this->assertSame($bytes, Storage::disk('private')->get($path));
        Http::assertSentCount(1);
        $this->actingAs($stranger)->get('/api/v1/rfqs/'.$rfq->id.'/buyer-documents/43')->assertNotFound();
        $this->actingAs($user)->get('/api/v1/rfqs/'.$rfq->id.'/buyer-documents/43')->assertOk()
            ->assertSee($bytes);
        Http::assertSentCount(1); // Local cache, not another 20-second proxy request.
        $changed = $doc;
        $changed['version'] = '1791940001123456';
        $this->assertNull(app(BuyerRfqFileCache::class)->cached($rfq, $changed));
    }
}
