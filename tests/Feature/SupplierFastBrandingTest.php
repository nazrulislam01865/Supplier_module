<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SupplierFastBrandingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', str_repeat('z', 40));
        config()->set('flowtrack_bridge.buyer_url', 'https://buyer.example.test');
    }

    private function postSigned(array $payload, bool $badSignature = false)
    {
        $event = '3f4e978d-50c8-4d50-95b2-0cdd3a704fc1';
        $body = json_encode(['event_id' => $event] + $payload, JSON_THROW_ON_ERROR);
        $time = (string) time();
        $signature = hash_hmac('sha256', $time."\n".$event."\n".$body, config('flowtrack_bridge.secret'));
        return $this->call('POST', '/api/v1/integrations/flowtrack/branding', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_X_FLOWTRACK_TIMESTAMP' => $time,
            'HTTP_X_FLOWTRACK_EVENT' => $event,
            'HTTP_X_FLOWTRACK_SIGNATURE' => $badSignature ? str_repeat('0', 64) : $signature,
        ], $body);
    }

    public function test_fast_signed_brand_update_changes_only_brand_and_revision(): void
    {
        $company = SupplierCompany::create([
            'reference' => 'FLOWTRACK-7', 'name' => 'Supplier Seven', 'active' => true,
            'flowtrack_supplier_id' => 7,
        ]);
        $body = [
            'supplier_id' => 7, 'revision' => 1760000000000099,
            'brand_name' => 'New FlowTrack',
            'brand_logo_path' => '/branding-assets/logo/abc.png/workspace/1',
        ];
        $this->postSigned($body)->assertOk();
        $company->refresh();
        $this->assertSame('Supplier Seven', $company->name);
        $this->assertSame('New FlowTrack', $company->flowtrack_brand_name);
        $this->assertSame('https://buyer.example.test/branding-assets/logo/abc.png/workspace/1', $company->flowtrack_brand_logo_url);
        $this->assertSame(1, (int) $company->dashboard_revision);

        $this->postSigned($body + [])->assertOk(); // Idempotent replay.
        $this->assertSame(1, (int) $company->fresh()->dashboard_revision);
        $this->postSigned(array_replace($body, [
            'revision' => 1760000000000098, 'brand_name' => 'Old FlowTrack',
        ]))->assertOk();
        $this->assertSame('New FlowTrack', $company->fresh()->flowtrack_brand_name);
        $this->postSigned(array_replace($body, [
            'revision' => 1760000000000100, 'brand_logo_path' => null,
        ]))->assertOk();
        $this->assertNull($company->fresh()->flowtrack_brand_logo_url);
    }

    public function test_signed_footer_update_reaches_login_and_stale_delivery_cannot_revert_it(): void
    {
        SupplierCompany::create([
            'reference' => 'FLOWTRACK-71', 'name' => 'Supplier Seventy One', 'active' => true,
            'flowtrack_supplier_id' => 71,
        ]);
        $footer = \App\Support\SupplierPortalFooter::defaults();
        $footer['copyright'] = '© Acme Buyer';
        $footer['links']['privacy']['url'] = 'https://acme.example/privacy';
        $footer['links']['terms']['enabled'] = false;
        $body = [
            'supplier_id' => 71, 'revision' => 1760000000001000,
            'brand_name' => 'Acme Buyer', 'brand_logo_path' => null,
            'footer' => $footer,
        ];
        $this->postSigned($body)->assertOk();
        $this->get('/login')->assertOk()
            ->assertSee('© Acme Buyer')
            ->assertSee('https://acme.example/privacy', false)
            ->assertDontSee('>Terms</');

        $stale = $body;
        $stale['revision']--;
        $stale['footer']['copyright'] = 'Stale copy';
        $this->postSigned($stale)->assertOk();
        $this->assertSame('© Acme Buyer', SupplierCompany::first()->flowtrack_portal_footer['copyright']);

        $unsafe = $body;
        $unsafe['revision']++;
        $unsafe['footer']['links']['privacy']['url'] = 'javascript:alert(1)';
        $this->postSigned($unsafe)->assertStatus(422);
        $this->assertSame('© Acme Buyer', SupplierCompany::first()->flowtrack_portal_footer['copyright']);
    }

    public function test_fast_brand_endpoint_requires_signature_and_known_supplier_and_safe_logo(): void
    {
        $body = [
            'supplier_id' => 432, 'revision' => 1760000000000000,
            'brand_name' => 'Safe Brand', 'brand_logo_path' => null,
        ];
        $this->postSigned($body, true)->assertStatus(401);
        $this->postSigned($body)->assertStatus(409);
        SupplierCompany::create([
            'reference' => 'FLOWTRACK-432', 'name' => 'Supplier',
            'flowtrack_supplier_id' => 432, 'active' => true,
        ]);
        $this->postSigned(array_replace($body, [
            'brand_logo_path' => 'https://untrusted.example.test/logo.png',
        ]))->assertStatus(422);
    }

    public function test_favicon_sync_is_signed_revision_safe_and_visible_on_login(): void
    {
        $company = SupplierCompany::create([
            'reference' => 'FLOWTRACK-217', 'name' => 'Favicon Supplier', 'active' => true,
            'flowtrack_supplier_id' => 217,
        ]);
        $body = [
            'supplier_id' => 217, 'revision' => 1760000000100000,
            'brand_name' => 'Favicon Brand', 'brand_logo_path' => null,
            'brand_favicon_path' => '/branding-assets/favicon/abc123.ico/workspace/1',
        ];
        $this->postSigned($body)->assertOk();
        $company->refresh();
        $this->assertSame('https://buyer.example.test/branding-assets/favicon/abc123.ico/workspace/1', $company->flowtrack_brand_favicon_url);
        $this->get('/login')->assertOk()->assertSee($company->flowtrack_brand_favicon_url, false);

        $this->postSigned(array_replace($body, [
            'revision' => $body['revision'] - 1, 'brand_favicon_path' => null,
        ]))->assertOk();
        $this->assertSame('https://buyer.example.test/branding-assets/favicon/abc123.ico/workspace/1', $company->fresh()->flowtrack_brand_favicon_url);

        $this->postSigned(array_replace($body, [
            'revision' => $body['revision'] + 1,
            'brand_favicon_path' => 'https://evil.example/steal.ico',
        ]))->assertStatus(422);
        $this->assertNotNull($company->fresh()->flowtrack_brand_favicon_url);

        $this->postSigned(array_replace($body, [
            'revision' => $body['revision'] + 2, 'brand_favicon_path' => null,
        ]))->assertOk();
        $this->assertNull($company->fresh()->flowtrack_brand_favicon_url);
    }

    public function test_favicon_must_use_the_configured_buyer_brand_asset_origin(): void
    {
        $this->assertNull(\App\Support\TrustedFlowtrackBrandUrl::favicon('javascript:alert(1)'));
        $this->assertNull(\App\Support\TrustedFlowtrackBrandUrl::favicon('/branding-assets/logo/a.png/workspace/1'));
        $this->assertSame(
            'https://buyer.example.test/branding-assets/favicon/safe.png/workspace/1',
            \App\Support\TrustedFlowtrackBrandUrl::favicon('/branding-assets/favicon/safe.png/workspace/1')
        );
    }
}
