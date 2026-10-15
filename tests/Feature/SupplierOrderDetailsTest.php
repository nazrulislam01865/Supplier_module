<?php

namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SupplierOrderDetailsTest extends TestCase
{
    use RefreshDatabase;

    private function company(string $reference, int $flowtrackId): array
    {
        $company = SupplierCompany::create([
            'reference' => $reference, 'name' => $reference, 'active' => true,
            'flowtrack_supplier_id' => $flowtrackId,
        ]);
        $user = SupplierUser::create([
            'supplier_company_id' => $company->id, 'name' => $reference,
            'email' => strtolower($reference).'@supplier.example',
            'password' => 'LongValidPassword123!', 'active' => true,
        ]);
        return [$company, $user];
    }

    private function order(SupplierCompany $company): SupplierResource
    {
        return SupplierResource::create([
            'supplier_company_id' => $company->id, 'type' => 'order',
            'external_reference' => 'ORD-112', 'status' => 'new',
            'payload' => [
                'title' => 'Order ORD-112', 'source_id' => 1099, 'revision' => 100,
                'order_status' => 'In progress',
                'items' => [['product_name' => 'Jersey', 'product_code' => 'PRD-100', 'quantity' => 25]],
                'stages' => [[
                    'name' => 'Artwork', 'status' => 'completed',
                    'tasks' => [['name' => 'Artwork approved', 'status' => 'completed']],
                ]],
                'documents' => [['id' => 551, 'name' => 'approved.pdf', 'size' => 7]],
            ],
        ]);
    }

    public function test_order_detail_requires_active_supplier_scope_and_shows_only_approved_data(): void
    {
        [$a, $alice] = $this->company('ONE', 101);
        [$b, $bob] = $this->company('TWO', 202);
        $order = $this->order($a);
        $this->get('/orders/'.$order->id)->assertRedirect('/login');
        $this->actingAs($bob)->get('/orders/'.$order->id)->assertNotFound();
        $this->actingAs($alice)->get('/orders/'.$order->id)
            ->assertOk()->assertSee('Jersey')->assertSee('Artwork approved')
            ->assertSee('approved.pdf')->assertSee('Download artwork')
            ->assertDontSee('customer@example.test');
        $this->actingAs($alice)->get('/?view=orders')->assertOk()
            ->assertSee('Download artwork')
            ->assertSee(route('supplier.orders.artwork', [$order->id, 551]));
        $a->update(['active' => false]);
        $this->actingAs($alice)->get('/orders/'.$order->id)->assertForbidden();
    }

    public function test_document_download_rejects_wrong_company_or_unlisted_file(): void
    {
        [$a, $alice] = $this->company('ONE', 101);
        [, $bob] = $this->company('TWO', 202);
        $order = $this->order($a);
        $this->actingAs($bob)->get('/orders/'.$order->id.'/artwork/551')->assertNotFound();
        $this->actingAs($alice)->get('/orders/'.$order->id.'/artwork/999')->assertNotFound();
    }

    public function test_document_download_uses_signed_server_request_only(): void
    {
        [$a, $alice] = $this->company('ONE', 101);
        $order = $this->order($a);
        config()->set('flowtrack_bridge.enabled', true);
        config()->set('flowtrack_bridge.buyer_url', 'http://127.0.0.1:8000');
        config()->set('flowtrack_bridge.secret', 'test-bridge-secret-at-least-32-characters');
        Http::fake(['http://127.0.0.1:8000/*' => Http::response('abcdefg', 200)]);
        $this->actingAs($alice)->get('/orders/'.$order->id.'/artwork/551')
            ->assertOk()->assertHeader('Content-Type', 'application/octet-stream')
            ->assertHeader('Cache-Control', 'private, no-store, max-age=0');
        Http::assertSent(fn ($request): bool => $request->hasHeader('X-FlowTrack-Signature')
            && str_contains($request->url(), '/order-artwork/1099/551'));
    }

    public function test_confirmed_order_lists_each_approved_file_with_a_protected_download_link(): void
    {
        [$company, $alice] = $this->company('ONE', 101);
        $order = $this->order($company);
        $payload = $order->payload;
        $payload['documents'][] = ['id' => 552, 'name' => 'final-artwork.ai', 'size' => 2432];
        $order->update(['payload' => $payload]);
        $this->actingAs($alice)->get('/orders/'.$order->id)->assertOk()
            ->assertSee('final-artwork.ai')
            ->assertSee(route('supplier.orders.artwork', [$order->id, 551]))
            ->assertSee(route('supplier.orders.artwork', [$order->id, 552]));
    }


    public function test_supplier_order_shows_only_first_five_stages_with_a_visible_default_when_billing_is_current(): void
    {
        [$company, $supplier] = $this->company('ONE', 101);
        $order = $this->order($company);
        $payload = $order->payload;
        $names = ['New Order', 'Artwork', 'Production', 'QC', 'Shipment', 'Billing', 'Payment'];
        $payload['workflow'] = [
            'current_phase_id' => 6,
            'stages' => array_map(static function ($name, $offset) {
                $id = $offset + 1;
                return [
                    'id' => $id,
                    'sequence' => $id,
                    'name' => $name,
                    'color' => '#008a7a',
                    'state' => $id < 6 ? 'completed' : ($id === 6 ? 'active' : 'locked'),
                    'state_label' => $id < 6 ? 'Completed' : 'Upcoming',
                    'progress' => $id < 6 ? 100 : 0,
                    'owner' => 'Unassigned',
                    'dependency' => '',
                    'required_complete' => $id < 6 ? 1 : 0,
                    'required_total' => 1,
                    'tasks' => [[
                        'id' => $id, 'code' => $id.'.1', 'title' => $name.' step',
                        'mode' => $id < 6 ? 'done' : 'locked',
                        'assignee' => 'Unassigned', 'required' => true,
                        'status' => 'Completed', 'due_date' => null, 'can_act' => false,
                    ]],
                ];
            }, $names, array_keys($names)),
        ];
        $order->update(['payload' => $payload]);

        $page = $this->actingAs($supplier)->get('/orders/'.$order->id)->assertOk();
        $page->assertSee('5 stages')
            ->assertSee('STAGE 5')
            ->assertDontSee('STAGE 6')
            ->assertDontSee('STAGE 7')
            ->assertDontSee('Billing tasks')
            ->assertDontSee('Payment tasks')
            ->assertSee('id="supplier-flow-tab-5"', false)
            ->assertSee('data-flow-panel="5"', false);

        // The current stage may be internal-only; a visible completed stage remains selected.
        $this->assertMatchesRegularExpression(
            '/id="supplier-flow-tab-5"[^>]*aria-selected="true"/',
            $page->getContent()
        );
        $this->assertLessThan(
            strpos($page->getContent(), 'supplier-prototype-side-column'),
            strpos($page->getContent(), 'supplierOrderWorkflow')
        );
    }

    public function test_revoked_order_is_never_listed_or_viewable(): void
    {
        [$a, $alice] = $this->company('ONE', 101);
        $order = $this->order($a);
        $order->update(['status' => 'revoked', 'payload' => ['revision' => 200, 'revoked' => true]]);
        $this->actingAs($alice)->get('/orders/'.$order->id)->assertNotFound();
        $this->actingAs($alice)->get('/orders/'.$order->id.'/artwork/551')->assertNotFound();
        $this->actingAs($alice)->get('/?view=orders')->assertOk()->assertDontSee('ORD-112');
    }
}
