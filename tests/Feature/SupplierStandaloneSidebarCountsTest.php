<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SupplierStandaloneSidebarCountsTest extends TestCase
{
    use RefreshDatabase;

    private function supplier(string $reference): array
    {
        $company = SupplierCompany::create([
            'reference' => $reference, 'name' => $reference, 'active' => true,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id,
            'name' => $reference.' User',
            'email' => strtolower($reference).'@example.test',
            'password' => 'ValidPassword123!', 'active' => true,
        ]);
        return [$company, $user];
    }

    private function resource(SupplierCompany $company, string $type, string $reference, string $status): SupplierResource
    {
        return SupplierResource::create([
            'supplier_company_id' => $company->id,
            'type' => $type,
            'external_reference' => $reference,
            'status' => $status,
            'payload' => $type === 'product' ? ['name' => 'Jersey', 'code' => $reference] : [],
        ]);
    }

    private function assertCounters(string $html, int $rfqs, int $orders, int $unread): void
    {
        $this->assertMatchesRegularExpression('/RFQs\s*<span class="nav-count">'.$rfqs.'<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Orders &amp; Requests\s*<span class="nav-count">'.$orders.'<\/span>/', $html);
        $this->assertMatchesRegularExpression('/Notifications\s*<span class="nav-count'.($unread ? '"' : ' hidden"').'>'.$unread.'<\/span>/', $html);
    }

    public function test_all_standalone_routes_keep_company_scoped_navigation_counters(): void
    {
        [$company, $user] = $this->supplier('OWN');
        [$other] = $this->supplier('OTHER');
        $this->resource($company, 'rfq', 'RFQ-OWN', 'open');
        $this->resource($company, 'rfq', 'RFQ-CLOSED', 'submitted');
        $order = $this->resource($company, 'order', 'ORD-OWN', 'new');
        $this->resource($company, 'order', 'ORD-OWN-2', 'in_progress');
        $this->resource($company, 'order', 'ORD-REVOKED', 'revoked');
        $product = $this->resource($company, 'product', 'SKU-OWN', 'active');
        $this->resource($other, 'order', 'ORD-PRIVATE', 'new');
        $this->resource($other, 'rfq', 'RFQ-PRIVATE', 'draft');

        $this->actingAs($user);
        foreach ([
            route('supplier.home'),
            route('supplier.products.prices'),
            route('supplier.products.prices.show', $product->id),
            route('supplier.products.prices.edit', $product->id),
            route('supplier.orders.show', $order->id),
        ] as $url) {
            $response = $this->get($url)->assertOk();
            $this->assertCounters($response->getContent(), 1, 2, 4);
        }
    }

    public function test_unread_sidebar_badge_is_hidden_after_marking_notifications_read(): void
    {
        [$company, $user] = $this->supplier('READ');
        $this->resource($company, 'order', 'ORD-READ', 'new');
        $product = $this->resource($company, 'product', 'SKU-READ', 'active');

        $this->actingAs($user);
        $this->assertCounters($this->get(route('supplier.products.prices'))->assertOk()->getContent(), 0, 1, 1);
        $this->postJson(route('supplier.dashboard.notifications.read'))->assertOk();
        $this->assertCounters($this->get(route('supplier.products.prices.show', $product->id))->assertOk()->getContent(), 0, 1, 0);
    }
}
