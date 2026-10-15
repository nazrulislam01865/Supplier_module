<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use App\Services\SupplierDashboardData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class SupplierDashboardLiveTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(string $code, int $sourceId): array
    {
        $company = SupplierCompany::create([
            'reference' => $code, 'name' => $code, 'active' => true,
            'flowtrack_supplier_id' => $sourceId,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id, 'name' => $code.' User',
            'email' => strtolower($code).'@example.test',
            'password' => 'long-test-password', 'active' => true,
        ]);
        return [$company, $user];
    }

    public function test_dashboard_uses_only_real_company_scoped_data_and_removes_requested_cards(): void
    {
        [$a, $user] = $this->supplier('ALPHA', 101);
        [$b] = $this->supplier('BRAVO', 102);
        SupplierResource::create([
            'supplier_company_id' => $a->id, 'type' => 'rfq', 'external_reference' => 'RFQ-ALPHA',
            'status' => 'open', 'due_at' => now()->addDays(3),
            'payload' => ['product_name' => 'Alpha Product'],
        ]);
        SupplierResource::create([
            'supplier_company_id' => $a->id, 'type' => 'order', 'external_reference' => 'ORD-ALPHA',
            'status' => 'new', 'payload' => ['title' => 'Alpha Order'],
        ]);
        SupplierResource::create([
            'supplier_company_id' => $b->id, 'type' => 'rfq', 'external_reference' => 'RFQ-SECRET',
            'status' => 'open', 'due_at' => now()->addDays(1),
            'payload' => ['product_name' => 'Secret Product'],
        ]);
        $data = app(SupplierDashboardData::class)->forUser($user);
        $this->assertSame(1, $data['rfqActionCount']);
        $this->assertSame(1, $data['activeOrderCount']);
        $this->assertSame(0, $data['submittedMonthCount']);
        $this->assertSame('RFQ-ALPHA', $data['nextDeadline']->external_reference);
        $this->assertSame(2, $data['unreadNotifications']);
        $this->assertCount(2, $data['recentActivity']);

        $ownRfq = SupplierResource::query()->where('supplier_company_id', $a->id)->where('type', 'rfq')->firstOrFail();
        $otherRfq = SupplierResource::query()->where('supplier_company_id', $b->id)->where('type', 'rfq')->firstOrFail();
        $this->actingAs($user)->getJson('/api/v1/dashboard/rfqs/'.$ownRfq->id)
            ->assertOk()->assertJsonPath('rfq.ref', 'RFQ-ALPHA');
        $this->getJson('/api/v1/dashboard/rfqs/'.$otherRfq->id)->assertNotFound();
        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('Alpha Product')->assertSee('ORD-ALPHA')
            ->assertDontSee('RFQ-SECRET')->assertDontSee('Document requests')
            ->assertDontSee('Pending bills')->assertDontSee('PAID BILLS')
            ->assertDontSee('data-view="company-documents"', false)->assertDontSee('Secure supplier session');
    }

    public function test_read_state_is_per_user_and_new_events_become_unread(): void
    {
        [$company, $user] = $this->supplier('READ', 111);
        $other = SupplierUser::create([
            'supplier_company_id' => $company->id, 'name' => 'Other',
            'email' => 'other@example.test', 'password' => 'long-test-password', 'active' => true,
        ]);
        $rfq = SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'rfq',
            'external_reference' => 'RFQ-READ', 'status' => 'open', 'payload' => ['product_name' => 'Product'],
        ]);
        $this->actingAs($user)->getJson('/dashboard/refresh')->assertOk()->assertJsonPath('unread', 1);
        $this->postJson('/dashboard/notifications/read')->assertOk()->assertJsonPath('unread', 0);
        $this->getJson('/dashboard/refresh')->assertOk()->assertJsonPath('unread', 0);
        $this->actingAs($other)->getJson('/dashboard/refresh')->assertOk()->assertJsonPath('unread', 1);
        $rfq->forceFill(['updated_at' => now()->addMinutes(3)])->saveQuietly();
        $this->actingAs($user)->getJson('/dashboard/refresh')->assertOk()->assertJsonPath('unread', 1);
    }

    public function test_dashboard_channel_rejects_another_suppliers_channel_before_bridge_call(): void
    {
        [, $user] = $this->supplier('AUTH', 121);
        Http::fake();
        $this->actingAs($user)->postJson('/dashboard/realtime-auth', [
            'socket_id' => '12.345', 'channel_name' => 'private-flowtrack.supplier.122.dashboard',
        ])->assertForbidden();
        Http::assertNothingSent();
    }
}
