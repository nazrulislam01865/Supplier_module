<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

final class SupplierDashboardSessionSecurityTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(): SupplierUser
    {
        $company = SupplierCompany::create([
            'reference' => 'SEC-01', 'name' => 'Secure Supplier',
            'active' => true, 'flowtrack_supplier_id' => 99331,
        ]);

        return SupplierUser::create([
            'supplier_company_id' => $company->id,
            'name' => 'Security User', 'email' => 'security@example.test',
            'password' => 'Correct-password-123!', 'active' => true,
        ]);
    }

    public function test_failed_logins_are_limited_per_account_and_ip_without_locking_out_successful_user(): void
    {
        $user = $this->supplier();
        $key = 'supplier-login:account:'.hash('sha256', 'security@example.test');
        $ipKey = 'supplier-login:ip:'.hash('sha256', '127.0.0.1');
        RateLimiter::clear($key);
        RateLimiter::clear($ipKey);

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('supplier.login.post'), [
                'email' => $user->email, 'password' => 'incorrect-password',
            ])->assertSessionHasErrors('email');
        }
        $this->postJson(route('supplier.login.post'), [
            'email' => $user->email, 'password' => 'Correct-password-123!',
        ])->assertStatus(429)->assertJsonValidationErrors('email');
        $this->assertGreaterThanOrEqual(5, RateLimiter::attempts($key));

        RateLimiter::clear($key);
        RateLimiter::clear($ipKey);
        $this->post(route('supplier.login.post'), [
            'email' => $user->email, 'password' => 'Correct-password-123!',
            'remember' => '1', // Older clients cannot bypass the idle policy.
        ])->assertRedirect(route('supplier.home'));
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull(session('supplier_last_activity_at'));
        $this->assertSame(30, config('session.lifetime'));
    }

    public function test_supplier_session_expires_at_thirty_minutes_on_all_protected_pages(): void
    {
        $user = $this->supplier();
        $expired = now()->timestamp - 1800;
        $this->actingAs($user)->withSession(['supplier_last_activity_at' => $expired]);

        $this->get(route('supplier.home'))->assertRedirect(route('supplier.login'));
        $this->assertGuest();
    }

    public function test_login_notice_is_red_and_only_visible_after_session_timeout(): void
    {
        $message = 'For security, sessions expire after 30 minutes of inactivity.';

        $this->get(route('supplier.login'))->assertOk()
            ->assertDontSee($message)
            ->assertDontSee('class="login-session-expired"', false);

        $user = $this->supplier();
        $this->actingAs($user)->withSession([
            'supplier_last_activity_at' => now()->timestamp - 1801,
        ]);

        $this->get(route('supplier.home'))
            ->assertRedirect(route('supplier.login'))
            ->assertSessionHas('supplier_session_expired', true);
        $this->assertGuest();

        $this->get(route('supplier.login'))->assertOk()
            ->assertSee($message)
            ->assertSee('class="login-session-expired" role="alert"', false);

        // The notice is one-time for a backend-detected timeout.
        $this->get(route('supplier.login'))->assertOk()->assertDontSee($message);

        // A browser-detected timeout can arrive after its logout fetch consumes
        // the server's flash; the dedicated URL still displays the same notice.
        $this->get(route('supplier.login', ['session_expired' => '1']))
            ->assertOk()->assertSee($message);
    }

    public function test_manual_logout_does_not_show_timeout_notice(): void
    {
        $this->actingAs($this->supplier())
            ->post(route('supplier.logout'))
            ->assertRedirect(route('supplier.login'));

        $this->get(route('supplier.login'))->assertOk()
            ->assertDontSee('For security, sessions expire after 30 minutes of inactivity.');
    }

    public function test_expired_ajax_session_returns_unauthorized_json(): void
    {
        $user = $this->supplier();
        $expired = now()->timestamp - 1801;
        $this->actingAs($user)->withSession(['supplier_last_activity_at' => $expired]);

        $this->postJson(route('supplier.session.activity'))
            ->assertStatus(401)->assertJsonPath('message', 'Your supplier session expired. Please sign in again.');
        $this->assertGuest();
    }

    public function test_background_notification_refresh_does_not_renew_activity_but_real_interaction_does(): void
    {
        $user = $this->supplier();
        $original = now()->timestamp - 420;
        $this->actingAs($user)->withSession(['supplier_last_activity_at' => $original]);

        $this->get(route('supplier.dashboard.revision'))->assertOk();
        $this->assertSame($original, session('supplier_last_activity_at'));
        $this->postJson(route('supplier.session.activity'))->assertNoContent();
        $this->assertGreaterThan($original, session('supplier_last_activity_at'));
    }

    public function test_activity_and_sidebar_logout_are_available_on_dashboard_and_order_details(): void
    {
        $user = $this->supplier();
        $dashboard = $this->actingAs($user)->get(route('supplier.home'))->assertOk();
        $dashboard->assertSee('class="supplier-sidebar-logout-form"', false);
        $dashboard->assertSee('supplier-session.js', false);
        $dashboard->assertSee('supplier-activity-list', false);
        $dashboard->assertSee(route('supplier.logout'), false);

        $pricing = $this->get(route('supplier.products.prices'))->assertOk();
        $pricing->assertSee('supplier-sidebar-logout-form', false);
        $pricing->assertSee('supplier-session.js', false);
        $this->post(route('supplier.logout'))->assertRedirect(route('supplier.login'));
        $this->assertGuest();
    }
}
