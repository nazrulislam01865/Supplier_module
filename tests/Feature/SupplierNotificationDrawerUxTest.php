<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SupplierNotificationDrawerUxTest extends TestCase
{
    use RefreshDatabase;

    public function test_drawer_uses_supplier_scoped_preview_with_filters_and_accessible_actions(): void
    {
        $company = SupplierCompany::create([
            'reference' => 'DRAWER-A', 'name' => 'Supplier A', 'active' => true,
            'flowtrack_supplier_id' => 941,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id, 'name' => 'Supplier Staff',
            'email' => 'drawer-a@example.test', 'password' => 'ValidPassword123!', 'active' => true,
        ]);
        $other = SupplierCompany::create([
            'reference' => 'DRAWER-B', 'name' => 'Supplier B', 'active' => true,
            'flowtrack_supplier_id' => 942,
        ]);

        SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'rfq',
            'external_reference' => 'RFQ-DRAWER-TEST', 'status' => 'open',
            'payload' => ['product_name' => 'Sample RFQ item'],
        ]);
        SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'order',
            'external_reference' => 'ORD-DRAWER-TEST', 'status' => 'new',
            'payload' => ['product_name' => 'Sample order item'],
        ]);
        SupplierResource::create([
            'supplier_company_id' => $other->id, 'type' => 'rfq',
            'external_reference' => 'RFQ-OTHER-SUPPLIER', 'status' => 'open',
            'payload' => ['product_name' => 'Private item'],
        ]);

        $this->actingAs($user);
        $html = $this->get(route('supplier.home'))->assertOk()->getContent();
        $start = strpos($html, 'id="notificationPanel"');
        $end = strpos($html, 'id="quoteSuccessModal"', $start);
        $this->assertNotFalse($start);
        $this->assertNotFalse($end);
        $drawer = substr($html, $start, $end - $start);

        $this->assertStringContainsString('aria-modal="true"', $drawer);
        $this->assertStringContainsString('data-notification-filter="unread"', $drawer);
        $this->assertStringContainsString('id="notificationDrawerTypeFilter"', $drawer);
        $this->assertStringContainsString('data-mark-notifications-read', $drawer);
        $this->assertStringContainsString('RFQ-DRAWER-TEST', $drawer);
        $this->assertStringContainsString('ORD-DRAWER-TEST', $drawer);
        $this->assertStringContainsString('data-notice-type="rfq"', $drawer);
        $this->assertStringContainsString('data-notice-type="order"', $drawer);
        $this->assertStringNotContainsString('RFQ-OTHER-SUPPLIER', $drawer);
    }
}
