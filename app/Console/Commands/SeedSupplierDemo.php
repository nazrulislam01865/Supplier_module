<?php
namespace App\Console\Commands;

use App\Models\SupplierCompany;
use App\Models\SupplierResource;
use Illuminate\Console\Command;

class SeedSupplierDemo extends Command
{
    protected $signature = 'supplier:seed-demo {reference}';
    protected $description = 'Load non-production RFQ fixtures into one supplier company';
    public function handle(): int
    {
        if (!app()->environment(['local','testing'])) {
            $this->error('Demo resources can only be created in local or testing environments.');
            return self::FAILURE;
        }
        $company = SupplierCompany::where('reference', $this->argument('reference'))->first();
        if (!$company) { $this->error('Company not found.'); return self::FAILURE; }
        $rows = [
            ['1 Layer Face Mask','PRD-034955',1000,'NEP',2],
            ['Recycled Cotton Tote Bag','PRD-039122',5000,'NEP',5],
            ['Stainless Steel Tumbler','PRD-041872',2500,'IID',8],
            ['RPET Drawstring Bag','PRD-033221',3000,'IID',11],
            ['Bamboo Fiber Lunch Box','PRD-028771',1200,'NEP',14],
            ['Travel Adapter Set','PRD-027018',800,'IID',17],
            ['Soft Touch Notebook','PRD-021882',4000,'NEP',20],
        ];
        foreach ($rows as $i=>$row) {
            [$name,$code,$quantity,$buyer,$days]=$row;
            SupplierResource::updateOrCreate([
                'supplier_company_id'=>$company->id, 'type'=>'rfq',
                'external_reference'=>'DEMO-RFQ-'.sprintf('%04d',$i+1),
            ],[
                'status'=>'open','due_at'=>now()->addDays($days),
                'payload'=>[
                    'product_name'=>$name,'product_code'=>$code,'quantity'=>$quantity,
                    'buyer_label'=>'Client '.$buyer,
                    'inquiry_reference'=>'DEMO-INQ-'.sprintf('%04d',$i+1),
                    'buyer_requirements'=>'Please review the shared product specification before quoting.',
                ],
            ]);
        }
        $this->info('Seven standalone demo RFQs created; no FlowTracker data accessed.');
        return self::SUCCESS;
    }
}
