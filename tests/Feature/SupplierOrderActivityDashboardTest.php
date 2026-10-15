<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use App\Services\SupplierDashboardData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class SupplierOrderActivityDashboardTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $code): array
    {
        $company = SupplierCompany::create([
            'reference' => $code, 'name' => $code,
            'flowtrack_supplier_id' => $code === 'TEST-A' ? 101 : 102,
            'active' => true,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id,
            'name' => $code.' operator',
            'email' => strtolower($code).'@example.test',
            'password' => 'LongPassword123!', 'active' => true,
        ]);
        $order = SupplierResource::create([
            'supplier_company_id' => $company->id, 'external_reference' => 'ORDER-'.$code,
            'type' => 'order', 'status' => 'new', 'payload' => ['title' => 'Test order'],
        ]);
        return [$company, $user, $order];
    }

    public function test_dashboard_only_shows_own_confirmed_order_actions_and_skips_revoked_orders(): void
    {
        [$a, $user, $orderA] = $this->company('TEST-A');
        [$b, , $orderB] = $this->company('TEST-B');
        $this->record($a->id, $user->id, $orderA->id, 'Published proof', 'Submitted');
        $this->record($b->id, null, $orderB->id, 'Secret supplier event', 'Completed');

        $feed = app(SupplierDashboardData::class)->forUser($user)['supplierOrderActions'];
        $this->assertCount(1, $feed);
        $this->assertSame('ORDER-TEST-A', $feed[0]['reference']);
        $this->assertSame('Published proof', $feed[0]['task']);
        $this->assertSame(route('supplier.orders.show', $orderA->id), $feed[0]['url']);

        $this->actingAs($user)->get(route('supplier.home'))->assertOk()
            ->assertSee('Supplier order activity')
            ->assertSee('Published proof')
            ->assertDontSee('Secret supplier event');
        $this->getJson(route('supplier.dashboard.refresh'))->assertOk()
            ->assertSee('Published proof')->assertDontSee('Secret supplier event');

        $orderA->update(['status' => 'revoked']);
        $this->assertCount(0, app(SupplierDashboardData::class)->forUser($user)['supplierOrderActions']);
    }

    public function test_dashboard_has_clear_empty_state_before_first_confirmed_submission(): void
    {
        [, $user] = $this->company('TEST-A');
        $this->actingAs($user)->get(route('supplier.home'))->assertOk()
            ->assertSee('No supplier order actions recorded yet.');
    }

    private function record(int $companyId, ?int $userId, int $orderId, string $title, string $status): void
    {
        DB::table('supplier_order_activities')->insert([
            'supplier_company_id' => $companyId,
            'supplier_resource_id' => $orderId,
            'supplier_user_id' => $userId,
            'event_id' => (string) Str::uuid(),
            'task_id' => 407,
            'stage_name' => 'Artwork',
            'task_title' => $title,
            'actor_name' => 'Supplier team',
            'decision' => 'upload',
            'task_status' => $status,
            'created_at' => now(),
        ]);
    }
}
