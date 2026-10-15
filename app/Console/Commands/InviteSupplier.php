<?php
namespace App\Console\Commands;

use App\Models\SupplierCompany;
use App\Models\SupplierUser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

class InviteSupplier extends Command
{
    protected $signature = 'supplier:invite {reference} {company} {email} {--contact=Supplier Contact}';
    protected $description = 'Invite a supplier account in the isolated portal (no FlowTracker access)';
    public function handle(): int
    {
        $reference = trim($this->argument('reference'));
        $email = Str::lower(trim($this->argument('email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($reference) > 80 || strlen($reference) < 2) {
            $this->error('Invalid reference or email.'); return self::FAILURE;
        }
        if (SupplierUser::where('email',$email)->exists() || SupplierCompany::where('reference',$reference)->exists()) {
            $this->error('A matching company or email already exists.'); return self::FAILURE;
        }
        DB::transaction(function () use ($reference, $email) {
            $company = SupplierCompany::create(['reference'=>$reference,'name'=>$this->argument('company'),'active'=>true]);
            SupplierUser::create([
                'supplier_company_id'=>$company->id,
                'name'=>$this->option('contact'), 'email'=>$email,
                'password'=>Str::random(80), 'active'=>true,
            ]);
        });
        $status = Password::broker('supplier_users')->sendResetLink(['email'=>$email]);
        if ($status !== Password::RESET_LINK_SENT) {
            $this->error('Supplier created, but invitation email failed. Fix mail then send password reset.');
            return self::FAILURE;
        }
        $this->info('Supplier created; an account setup email was sent.');
        return self::SUCCESS;
    }
}
