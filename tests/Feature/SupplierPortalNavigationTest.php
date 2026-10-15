<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SupplierPortalNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(): array
    {
        $company = SupplierCompany::create([
            'reference' => 'NAV', 'name' => 'Navigation Test', 'active' => true,
            'flowtrack_supplier_id' => 834,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id, 'name' => 'Navigation User',
            'email' => 'navigation@example.test', 'password' => 'LongValidPassword123!', 'active' => true,
        ]);
        return [$company, $user];
    }

    public function test_deep_link_from_order_details_opens_orders_without_needing_javascript(): void
    {
        [$company, $user] = $this->supplier();
        $order = SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'order',
            'external_reference' => 'ORD-NAV', 'status' => 'new',
            'payload' => ['title' => 'Order ORD-NAV', 'items' => []],
        ]);

        $details = $this->actingAs($user)->get(route('supplier.orders.show', $order->id))->assertOk();
        $details->assertSee(route('supplier.home').'?view=orders', false);

        $orders = $this->get(route('supplier.home', ['view' => 'orders']))->assertOk();
        $orders->assertSee('ORD-NAV');
        $this->assertMatchesRegularExpression('/id="view-orders"\s+class="app-view"/', $orders->getContent());
        $this->assertMatchesRegularExpression('/id="view-dashboard"\s+class="app-view hidden"/', $orders->getContent());
        $this->assertMatchesRegularExpression('/class="nav-item active"\s+data-view="orders"/', $orders->getContent());

        $paged = $this->get(route('supplier.home', ['orders_page' => 1]))->assertOk();
        $this->assertMatchesRegularExpression('/id="view-orders"\s+class="app-view"/', $paged->getContent());
    }

    public function test_workspace_deep_links_are_server_selected_and_logo_opens_dashboard(): void
    {
        [, $user] = $this->supplier();
        $this->actingAs($user);
        foreach (['dashboard', 'quotations', 'profile', 'notifications', 'help'] as $view) {
            $page = $this->get(route('supplier.home', ['view' => $view]))->assertOk();
            $this->assertMatchesRegularExpression('/id="view-'. $view .'"\s+class="app-view"/', $page->getContent());
        }
        $invalid = $this->get(route('supplier.home', ['view' => 'unknown']))->assertOk();
        $this->assertMatchesRegularExpression('/id="view-dashboard"\s+class="app-view"/', $invalid->getContent());
        $this->assertMatchesRegularExpression('/<a class="brand" href="[^\"]*\?view=dashboard" aria-label="Go to dashboard">/', $invalid->getContent());
    }

    public function test_rfq_and_quotation_workflow_share_sidebar_with_supplier_task_actions(): void
    {
        [, $user] = $this->supplier();
        $page = $this->actingAs($user)->get(route('supplier.home', ['view' => 'quotations']))->assertOk();
        $page->assertSee('id="sidebar"', false);
        $page->assertSee('id="view-rfq"', false);
        $page->assertSee('id="view-quote-workspace"', false);
        $page->assertSee('id="supplierInquiryTaskCard"', false);
        $page->assertSee('id="supplierInquiryTaskFile"', false);
        $page->assertSee('id="supplierInquiryTaskUpload"', false);
        $page->assertSee('id="supplierInquiryTaskComplete"', false);
        $page->assertSee('id="supplierInquiryTaskCompleted"', false);
    }

    public function test_order_details_notification_dot_respects_supplier_scoped_per_user_read_state(): void
    {
        [$company, $user] = $this->supplier();
        $otherUser = SupplierUser::create([
            'supplier_company_id' => $company->id, 'name' => 'Other User',
            'email' => 'second-nav@example.test', 'password' => 'LongValidPassword123!', 'active' => true,
        ]);
        $order = SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'order',
            'external_reference' => 'ORD-UNREAD', 'status' => 'new',
            'payload' => ['title' => 'Order ORD-UNREAD', 'items' => []],
        ]);

        $this->actingAs($user)->get(route('supplier.orders.show', $order->id))
            ->assertOk()->assertSee('<span class="notification-dot"></span>', false);
        $this->postJson(route('supplier.dashboard.notifications.read'))->assertOk();
        $this->get(route('supplier.orders.show', $order->id))
            ->assertOk()->assertDontSee('<span class="notification-dot"></span>', false);
        $this->actingAs($otherUser)->get(route('supplier.orders.show', $order->id))
            ->assertOk()->assertSee('<span class="notification-dot"></span>', false);
    }
}
