<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

final class SupplierNotificationPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $ref): array
    {
        $company = SupplierCompany::create([
            'reference' => $ref, 'name' => $ref, 'active' => true,
            'flowtrack_supplier_id' => $ref === 'PAG-A' ? 901 : 902,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id, 'name' => 'Staff '.$ref,
            'email' => strtolower($ref).'@example.test',
            'password' => 'LongValidPassword123!', 'active' => true,
        ]);

        return [$company, $user];
    }

    private function notification(SupplierCompany $company, string $reference, string $type = 'order'): SupplierResource
    {
        return SupplierResource::create([
            'supplier_company_id' => $company->id,
            'type' => $type,
            'external_reference' => $reference,
            'status' => $type === 'order' ? 'new' : 'open',
            'payload' => ['product_name' => 'Product '.$reference],
        ]);
    }

    /**
     * The main portal renders hidden Orders and the bell drawer alongside the
     * notification center. Assertions against the entire response can therefore
     * see unrelated order references even when notifications are correctly filtered.
     * Scope row assertions to the server-rendered notification-center section.
     */
    private function notificationCenterMarkup(TestResponse $response): string
    {
        $html = $response->getContent();
        $start = strpos($html, 'id="notificationCenterList"');
        $this->assertNotFalse($start, 'The notification center must appear in the portal response.');
        $end = strpos($html, '</section>', $start);
        $this->assertNotFalse($end, 'The notification center must be enclosed by its view section.');

        return substr($html, $start, $end - $start);
    }

    public function test_notifications_are_paginated_and_scoped_to_current_supplier(): void
    {
        [$company, $user] = $this->company('PAG-A');
        [$other] = $this->company('PAG-B');
        foreach (range(1, 34) as $i) {
            $this->notification($company, sprintf('ORD-PAG-%02d', $i));
        }
        $this->notification($other, 'ORD-SECRET-COMPANY');
        $this->actingAs($user);

        $page1 = $this->get(route('supplier.home', ['view' => 'notifications']))->assertOk();
        $page1->assertSee('Showing 1–15 of 34 updates');
        $page1->assertSee('Page 1 of 3');
        $page1->assertSee('notifications_page=2', false);
        $page1Notifications = $this->notificationCenterMarkup($page1);
        $this->assertStringNotContainsString('ORD-SECRET-COMPANY', $page1Notifications);
        $this->assertStringContainsString('ORD-PAG-34', $page1Notifications);
        $this->assertStringNotContainsString('ORD-PAG-01', $page1Notifications);
        $this->assertSame(15, substr_count($page1Notifications, 'class="supplier-notification-row'));

        // The on-demand endpoint must return the same supplier-scoped first page.
        $firstPageHtml = $this->getJson(route('supplier.dashboard.notifications.center'))
            ->assertOk()->json('html');
        $this->assertSame(15, substr_count($firstPageHtml, 'class="supplier-notification-row'));
        $this->assertStringNotContainsString('ORD-PAG-01', $firstPageHtml);
        $this->assertStringNotContainsString('ORD-SECRET-COMPANY', $firstPageHtml);

        $page3 = $this->get(route('supplier.home', [
            'view' => 'notifications', 'notifications_page' => 3,
        ]))->assertOk();
        $page3->assertSee('Showing 31–34 of 34 updates');
        $page3Notifications = $this->notificationCenterMarkup($page3);
        $this->assertStringContainsString('ORD-PAG-01', $page3Notifications);
        $this->assertStringNotContainsString('ORD-SECRET-COMPANY', $page3Notifications);
        $this->assertSame(4, substr_count($page3Notifications, 'class="supplier-notification-row'));
        $page3->assertSee('id="view-notifications" class="app-view"', false);
    }

    public function test_filtering_by_type_and_unread_is_applied_before_pagination(): void
    {
        [$company, $user] = $this->company('PAG-A');
        $read = $this->notification($company, 'ORD-OLD', 'order');
        $read->forceFill(['updated_at' => now()->subMinutes(20)])->saveQuietly();
        $user->forceFill(['notifications_read_at' => now()->subMinutes(10)])->save();
        $fresh = $this->notification($company, 'RFQ-NEW', 'rfq');
        $fresh->forceFill(['updated_at' => now()])->saveQuietly();
        $this->actingAs($user);

        $filtered = $this->get(route('supplier.home', [
            'view' => 'notifications', 'notification_type' => 'rfq', 'notification_state' => 'unread',
        ]))->assertOk();
        $filtered->assertSee('Showing 1–1 of 1 updates');
        $filteredNotifications = $this->notificationCenterMarkup($filtered);
        $this->assertStringContainsString('RFQ-NEW', $filteredNotifications);
        $this->assertStringNotContainsString('ORD-OLD', $filteredNotifications);
        $this->assertSame(1, substr_count($filteredNotifications, 'class="supplier-notification-row'));

        // Also verify the filtered async response (which the live UI loads).
        $filteredJsonHtml = $this->getJson(route('supplier.dashboard.notifications.center', [
            'notification_type' => 'rfq', 'notification_state' => 'unread',
        ]))->assertOk()->json('html');
        $this->assertStringContainsString('RFQ-NEW', $filteredJsonHtml);
        $this->assertStringNotContainsString('ORD-OLD', $filteredJsonHtml);
        $this->assertSame(1, substr_count($filteredJsonHtml, 'class="supplier-notification-row'));

        $this->getJson(route('supplier.dashboard.notifications.center', [
            'notification_type' => 'order', 'notification_state' => 'unread',
        ]))->assertOk()->assertJsonStructure(['html'])
            ->assertSee('There are no unread updates matching these filters.');

        $this->postJson(route('supplier.dashboard.notifications.read'))->assertOk()->assertJsonPath('unread', 0);
        $this->get(route('supplier.home', [
            'view' => 'notifications', 'notification_state' => 'unread',
        ]))->assertOk()->assertSee('You’re all caught up');
    }

    public function test_drawer_still_shows_only_preview_and_center_is_loaded_on_demand(): void
    {
        [$company, $user] = $this->company('PAG-A');
        foreach (range(1, 36) as $i) {
            $this->notification($company, sprintf('NOTICE-%02d', $i), $i % 2 ? 'rfq' : 'order');
        }
        $this->actingAs($user);
        $dashboard = $this->get(route('supplier.home'))->assertOk();
        $dashboard->assertSee('data-loaded="false"', false);
        $dashboard->assertDontSee('Page 1 of 3');

        $center = $this->getJson(route('supplier.dashboard.notifications.center'))
            ->assertOk()->assertJsonStructure(['html']);
        $content = $center->json('html');
        $this->assertSame(15, substr_count($content, 'class="supplier-notification-row'));
        $this->assertStringContainsString('Page 1 of 3', $content);
        $this->assertStringNotContainsString('ORD-SECRET-COMPANY', $content);
    }
}
