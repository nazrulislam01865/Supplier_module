<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Auth\Notifications\ResetPassword;
use Tests\TestCase;

class FlowtrackSupplierCatalogApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
    }

    private function send(string $route, array $data, bool $sign = true)
    {
        $data['event_id'] = 'c09541f5-1bc7-4564-abd6-0d40ec325d21';
        $body = json_encode($data, JSON_THROW_ON_ERROR);
        $time = (string) time();
        $headers = ['CONTENT_TYPE' => 'application/json'];
        if ($sign) {
            $headers += [
                'X-FlowTrack-Timestamp' => $time,
                'X-FlowTrack-Event' => $data['event_id'],
                'X-FlowTrack-Signature' => hash_hmac('sha256', $time."\n".$data['event_id']."\n".$body, config('flowtrack_bridge.secret')),
            ];
        }
        return $this->call('POST', '/api/v1/integrations/flowtrack/'.$route, [], [], [], $headers, $body);
    }

    private function supplier(int $id, array $products = []): array
    {
        $products = array_map(static function (array $product): array {
            $tiers = $product['supplier_prices'] ?? [];
            return $product + [
                'supplier_prices' => $tiers,
                'price_version' => hash('sha256', json_encode($tiers, JSON_THROW_ON_ERROR)),
            ];
        }, $products);
        return [
            'supplier_id' => $id, 'catalog_revision' => 100,
            'reference' => 'FLOWTRACK-'.$id,
            'name' => 'Supplier '.$id, 'short_code' => 'S'.$id,
            'active' => true, 'products' => $products,
        ];
    }

    public function test_supplier_and_products_are_isolated_updated_and_removed_without_client_fields(): void
    {
        $this->send('suppliers', $this->supplier(101, [
            ['id' => 11, 'code' => 'PRD-000011', 'name' => 'T-shirt', 'active' => true],
        ]))->assertOk();
        $this->send('suppliers', $this->supplier(202, [
            ['id' => 22, 'code' => 'PRD-000022', 'name' => 'Socks', 'active' => true],
        ]))->assertOk();
        $this->send('suppliers', $this->supplier(101, []))->assertOk();
        $one = SupplierCompany::where('flowtrack_supplier_id', 101)->firstOrFail();
        $two = SupplierCompany::where('flowtrack_supplier_id', 202)->firstOrFail();
        $this->assertSame(0, $one->resources()->where('type', 'product')->count());
        $this->assertSame(1, $two->resources()->where('type', 'product')->count());
        $this->assertSame(2, SupplierCompany::count());
        $this->assertArrayNotHasKey('client', $two->resources()->first()->payload);
    }

    public function test_admin_provisions_an_account_but_duplicate_email_cannot_be_taken_by_another_supplier(): void
    {
        $this->send('suppliers', $this->supplier(101))->assertOk();
        $this->send('suppliers', $this->supplier(202))->assertOk();
        $data = [
            'supplier_id' => 101, 'username' => 'supplier101',
            'email' => 'a@example.test', 'name' => 'Contact',
            'password' => 'StrongPassword2026',
        ];
        $this->send('supplier-access', $data)->assertOk();
        $user = SupplierUser::where('email', 'a@example.test')->firstOrFail();
        $this->assertNotSame($data['password'], $user->password);
        $this->assertSame(101, $user->company->flowtrack_supplier_id);
        $this->send('supplier-access', array_merge($data, ['supplier_id' => 202]))->assertStatus(409);
        $this->assertSame(1, SupplierUser::count());
    }

    public function test_unsigned_requests_and_unmapped_accounts_cannot_be_provisioned(): void
    {
        $this->send('suppliers', $this->supplier(101), false)->assertUnauthorized();
        $this->assertSame(0, SupplierCompany::count());
        $this->send('supplier-access', [
            'supplier_id' => 101, 'username' => 'supplier101',
            'email' => 'a@example.test', 'name' => 'Contact',
            'password' => 'StrongPassword2026',
        ])->assertStatus(409);
    }
    public function test_existing_supplier_credentials_can_be_edited_without_changing_password(): void
    {
        $this->send('suppliers', $this->supplier(101))->assertOk();
        $base = [
            'supplier_id' => 101, 'username' => 'supplier101',
            'email' => 'first@example.test', 'name' => 'Contact',
            'password' => 'StrongPassword2026',
            'require_password_change' => true,
        ];
        $this->send('supplier-access', $base)->assertOk();
        $user = SupplierUser::where('email', 'first@example.test')->firstOrFail();
        $oldHash = $user->password;
        $this->assertTrue($user->must_change_password);

        $this->actingAs($user)->get('/')->assertRedirect(route('supplier.password.change'));
        $this->actingAs($user)->getJson('/api/v1/company-documents')->assertStatus(423);

        $this->send('supplier-access', [
            'supplier_id' => 101, 'username' => 'renamed101',
            'email' => 'updated@example.test', 'name' => 'Changed contact',
        ])->assertOk();
        $user->refresh();
        $this->assertSame($oldHash, $user->password);
        $this->assertSame('renamed101', $user->username);
        $this->assertTrue($user->must_change_password);
        $this->assertSame('updated@example.test', $user->email);
        $this->actingAs($user)->post('/change-password', [
            'current_password' => 'StrongPassword2026',
            'password' => 'UpdatedStrongPassword2026',
            'password_confirmation' => 'UpdatedStrongPassword2026',
        ])->assertRedirect(route('supplier.home'));
        $this->assertFalse($user->fresh()->must_change_password);

        $this->send('supplier-access', array_merge($base, [
            'email' => 'updated@example.test', 'username' => 'renamed101',
            'password' => 'AnotherStrongPassword2026',
            'require_password_change' => false,
        ]))->assertOk();
        $user->refresh();
        $this->assertTrue(Hash::check('AnotherStrongPassword2026', $user->password));
        $this->assertFalse($user->must_change_password);
    }

    public function test_reset_link_is_signed_and_delivered_only_for_linked_admin(): void
    {
        Notification::fake();
        $this->send('suppliers', $this->supplier(101))->assertOk();
        $this->send('supplier-access', [
            'supplier_id' => 101, 'username' => 'supplier101',
            'email' => 'reset@example.test', 'name' => 'Contact',
            'password' => 'StrongPassword2026',
        ])->assertOk();
        $this->send('supplier-access/reset', ['supplier_id' => 101], false)->assertUnauthorized();
        $this->send('supplier-access/reset', ['supplier_id' => 202])->assertStatus(409);
        $this->send('supplier-access/reset', ['supplier_id' => 101])->assertOk();
        Notification::assertSentTo(SupplierUser::where('email', 'reset@example.test')->firstOrFail(), ResetPassword::class);
    }

    public function test_catalog_accepts_products_without_price_tiers_and_empty_assignments(): void
    {
        $product = ['id' => 51, 'code' => 'PRD-000051', 'name' => 'Awaiting supplier cost', 'active' => true];
        $this->send('suppliers', $this->supplier(303, [$product]))->assertOk();
        $company = SupplierCompany::where('flowtrack_supplier_id', 303)->firstOrFail();
        $this->assertSame([], $company->resources()->where('type', 'product')->firstOrFail()->payload['supplier_prices']);
        $this->send('suppliers', $this->supplier(303, []))->assertOk();
        $this->assertSame(0, $company->resources()->where('type', 'product')->count());
    }

    public function test_catalog_rejects_invalid_actual_price_rows(): void
    {
        $product = ['id' => 52, 'code' => 'PRD-000052', 'name' => 'Invalid cost', 'active' => true,
            'supplier_prices' => [['quantity' => 100, 'price' => -1]]];
        $this->send('suppliers', $this->supplier(304, [$product]))
            ->assertStatus(422)->assertJsonPath('message', 'Invalid supplier catalogue.');
        $this->assertSame(0, SupplierCompany::where('flowtrack_supplier_id', 304)->count());
    }

    public function test_initial_password_allows_eight_characters_but_rejects_seven(): void
    {
        $this->send('suppliers', $this->supplier(305))->assertOk();
        $base = ['supplier_id' => 305, 'username' => 's305',
            'email' => 's305@example.test', 'name' => 'S305'];
        $this->send('supplier-access', $base + ['password' => '1234567'])->assertStatus(422);
        $this->send('supplier-access', $base + ['password' => '12345678'])->assertOk();
        $this->assertTrue(Hash::check('12345678', SupplierUser::where('email', 's305@example.test')->firstOrFail()->password));
    }

    public function test_passwordless_account_creation_is_refused(): void
    {
        $this->send('suppliers', $this->supplier(101))->assertOk();
        $this->send('supplier-access', [
            'supplier_id' => 101, 'username' => 'supplier101',
            'email' => 'new@example.test', 'name' => 'Contact',
        ])->assertStatus(409);
        $this->assertSame(0, SupplierUser::count());
    }

    public function test_signed_catalog_optionally_persists_product_image_url_without_changing_price_version(): void
    {
        $product = ['id' => 81, 'code' => 'PRD-000081', 'name' => 'Image test', 'active' => true,
            'image_url' => 'https://flowtrack.example.test/storage/products/81.jpg',
            'supplier_prices' => [['quantity' => 100, 'price' => 4.75]]];
        $this->send('suppliers', $this->supplier(310, [$product]))->assertOk();
        $company = SupplierCompany::where('flowtrack_supplier_id', 310)->firstOrFail();
        $payload = $company->resources()->where('type', 'product')->firstOrFail()->payload;
        $this->assertSame($product['image_url'], $payload['image_url']);
        $this->assertSame(hash('sha256', json_encode($product['supplier_prices'], JSON_THROW_ON_ERROR)), $payload['price_version']);
    }

}
