<?php
namespace Tests\Feature;

use App\Models\SupplierCompany;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SupplierLoginBrandResponsiveTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_synced_brand_logo_without_duplicate_name(): void
    {
        SupplierCompany::create([
            'reference' => 'FT-SUP-9',
            'name' => 'Example Supplier',
            'active' => true,
            'flowtrack_brand_name' => 'FlowTrack Official',
            'flowtrack_brand_logo_url' => 'https://buyer.example.test/branding-assets/logo/brand.webp/workspace/1',
        ]);

        $this->get('/login')->assertOk()
            ->assertSee('class="login-brand-logo"', false)
            ->assertSee('https://buyer.example.test/branding-assets/logo/brand.webp/workspace/1', false)
            ->assertSee('alt="FlowTrack Official"', false)
            ->assertDontSee('Example Supplier');
    }

    public function test_login_uses_defaults_without_a_synced_footer(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('Privacy')
            ->assertSee('Terms')
            ->assertSee('Help center')
            ->assertDontSee('href="#">Privacy', false);
    }

    public function test_login_falls_back_to_brand_name_when_no_synced_logo_exists(): void
    {
        $this->get('/login')->assertOk()
            ->assertSee('class="brand-mark"', false)
            ->assertSee('FlowTrack');
    }
}
