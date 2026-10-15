<?php
namespace Tests\Feature;

use App\Jobs\WarmSupplierProductImages;
use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class SupplierImageAndBrandSyncTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        // Exercise the async warm-up branch while intercepting jobs in the test.
        config()->set('queue.default', 'database');
        Storage::fake('private');
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', str_repeat('s', 48));
        config()->set('flowtrack_bridge.buyer_url', 'https://buyer.example.test');
    }

    private function catalog(int $supplierId, array $products, ?string $logo = null): void
    {
        $event = 'c09541f5-1bc7-4564-abd6-0d40ec325d21';
        $data = [
            'event_id' => $event, 'supplier_id' => $supplierId,
            'catalog_revision' => 1760000000000000, 'reference' => 'FLOWTRACK-'.$supplierId,
            'name' => 'Supplier '.$supplierId, 'short_code' => 'S'.$supplierId,
            'active' => true, 'brand_name' => 'FlowTracker Official',
            'brand_logo_path' => $logo, 'products' => $products,
        ];
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $time = (string) time();
        $this->call('POST', '/api/v1/integrations/flowtrack/suppliers', [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_X_FLOWTRACK_TIMESTAMP' => $time,
            'HTTP_X_FLOWTRACK_EVENT' => $event,
            'HTTP_X_FLOWTRACK_SIGNATURE' => hash_hmac('sha256', $time."\n".$event."\n".$body, config('flowtrack_bridge.secret')),
        ], $body)->assertOk();
    }

    private function product(int $id): array
    {
        return [
            'id' => $id, 'name' => 'Official Product', 'code' => 'PRD-'.$id,
            'active' => true, 'supplier_prices' => [['quantity' => 100, 'price' => 1.50]],
            'price_version' => str_repeat('b', 64),
            'image_version' => str_repeat('c', 64), 'image_extension' => 'png',
        ];
    }

    public function test_signed_catalogue_updates_brand_and_dispatches_async_image_warmup(): void
    {
        $this->catalog(5, [$this->product(90)], '/branding-assets/logo/branding.png');
        $company = SupplierCompany::where('flowtrack_supplier_id', 5)->firstOrFail();
        $this->assertSame('FlowTracker Official', $company->flowtrack_brand_name);
        $this->assertSame('https://buyer.example.test/branding-assets/logo/branding.png', $company->flowtrack_brand_logo_url);
        $this->assertSame(str_repeat('c', 64), $company->resources()->where('type', 'product')->firstOrFail()->payload['image_version']);
        Queue::assertPushed(WarmSupplierProductImages::class);
    }

    public function test_signed_workspace_specific_logo_and_supplier_company_name_are_updated(): void
    {
        $this->catalog(5, [$this->product(90)], '/branding-assets/logo/branding.png/workspace/14');
        $company = SupplierCompany::where('flowtrack_supplier_id', 5)->firstOrFail();
        $this->assertSame('Supplier 5', $company->name);
        $this->assertSame('FlowTracker Official', $company->flowtrack_brand_name);
        $this->assertSame('https://buyer.example.test/branding-assets/logo/branding.png/workspace/14',
            $company->flowtrack_brand_logo_url);

        $user = SupplierUser::create(['supplier_company_id' => $company->id, 'name' => 'Admin',
            'email' => 'branding@example.test', 'password' => 'LongValidPassword123!', 'active' => true]);
        $this->actingAs($user)->get('/products/prices')->assertOk()->assertSee('FlowTracker Official')
            ->assertSee('Supplier 5')
            ->assertSee('/branding-assets/logo/branding.png/workspace/14', false)
            ->assertSee('data-flowtrack-brand-name class="hidden"', false)
            ->assertDontSee('class="portal-label"', false);
    }

    public function test_cached_product_image_requires_login_and_correct_supplier_scope(): void
    {
        $this->catalog(5, [$this->product(90)]);
        $this->catalog(6, [$this->product(91)]);
        $mine = SupplierCompany::where('flowtrack_supplier_id', 5)->firstOrFail();
        $other = SupplierCompany::where('flowtrack_supplier_id', 6)->firstOrFail();
        $ownProduct = SupplierResource::where('supplier_company_id', $mine->id)->firstOrFail();
        $otherProduct = SupplierResource::where('supplier_company_id', $other->id)->firstOrFail();
        $path = app(\App\Services\SupplierImageCache::class)->path($mine->id, $ownProduct->id, str_repeat('c', 64), 'png');
        Storage::disk('private')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScLttAAAAABJRU5ErkJggg=='));
        $this->get('/products/prices/'.$ownProduct->id.'/image')->assertRedirect('/login');
        $user = SupplierUser::create(['supplier_company_id' => $mine->id, 'name' => 'Supplier admin',
            'email' => 'a@example.test', 'password' => 'Password123!', 'active' => true]);
        $this->actingAs($user)->get('/products/prices/'.$ownProduct->id.'/image')->assertOk();
        $this->actingAs($user)->get('/products/prices/'.$otherProduct->id.'/image')->assertNotFound();
    }
}
