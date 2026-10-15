<?php
namespace Tests\Feature;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function makeSupplier(string $label): array
    {
        $company=SupplierCompany::create(['reference'=>'DEMO-'.$label,'name'=>$label,'active'=>true]);
        $user=SupplierUser::create(['supplier_company_id'=>$company->id,'name'=>'Account '.$label,
            'email'=>strtolower($label).'@supplier.example','password'=>'CorrectHorseBattery9!','active'=>true]);
        $resource=SupplierResource::create(['supplier_company_id'=>$company->id,'type'=>'rfq',
            'external_reference'=>'RFQ-'.$label,'status'=>'open','due_at'=>now()->addDays(5),
            'payload'=>['product_name'=>'Example','product_code'=>'DEMO','quantity'=>1000,'buyer_label'=>'Buyer']]);
        return [$company,$user,$resource];
    }

    public function test_supplier_cannot_read_or_write_other_company_rfq(): void
    {
        [, $one]= $this->makeSupplier('ONE');
        [, , $twoRfq]= $this->makeSupplier('TWO');
        $this->actingAs($one)->getJson('/api/v1/rfqs/'.$twoRfq->id.'/quotation')->assertNotFound();
        $this->actingAs($one)->putJson('/api/v1/rfqs/'.$twoRfq->id.'/quotation', [])->assertNotFound();
    }

    public function test_supplier_cannot_access_internal_flowtracker_routes(): void
    {
        [, $user]= $this->makeSupplier('ONE');
        $this->actingAs($user)->get('/administration')->assertNotFound();
        $this->actingAs($user)->get('/rfq/random-token')->assertNotFound();
    }

    public function test_guest_is_redirected_from_supplier_portal(): void
    {
        $this->get('/')->assertRedirect('/login');
        $this->getJson('/api/v1/rfqs/1/quotation')->assertUnauthorized();
    }

    public function test_disabled_supplier_is_denied(): void
    {
        [$company,$user]= $this->makeSupplier('ONE');
        $company->update(['active'=>false]);
        $this->actingAs($user)->get('/')->assertForbidden();
    }

    public function test_quote_can_be_submitted_without_optional_documents(): void
    {
        [, $user,$rfq]= $this->makeSupplier('ONE');
        $this->actingAs($user)->postJson('/api/v1/rfqs/'.$rfq->id.'/submit', [
            'contact'=>'Account ONE','email'=>$user->email,'phone'=>'123456789',
            'currency'=>'USD','unit_price'=>'0.18','moq'=>500,'tooling'=>'0','sample'=>'0',
            'discount'=>'0','tax'=>'Excluded','production_lead'=>15,'sample_lead'=>5,
            'incoterm'=>'FOB','shipping_port'=>'Shanghai','delivery_date'=>now()->addDays(10)->toDateString(),
            'validity'=>30,'compliance'=>'Yes, fully compliant','accuracy'=>true,'authority'=>true,
        ])->assertOk()->assertJsonPath('sync_status', 'pending');
    }
}
