<?php

namespace App\Console\Commands;

use App\Models\SupplierCompany;
use Illuminate\Console\Command;
use Illuminate\Database\UniqueConstraintViolationException;

/** Explicit administrative mapping; a supplier user cannot choose a FlowTracker identity. */
final class LinkFlowtrackSupplier extends Command
{
    protected $signature = 'supplier:link-flowtrack {company_reference} {flowtrack_supplier_id}';
    protected $description = 'Map an existing supplier account to a FlowTracker supplier master record ID';

    public function handle(): int
    {
        $company = SupplierCompany::where('reference', $this->argument('company_reference'))->first();
        $id = filter_var($this->argument('flowtrack_supplier_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! $company || $id === false) {
            $this->error('Unknown company reference or invalid numeric FlowTracker supplier ID.');
            return self::FAILURE;
        }
        try {
            $company->update(['flowtrack_supplier_id' => $id]);
        } catch (UniqueConstraintViolationException) {
            $this->error('That FlowTracker supplier is already linked to another portal company.');
            return self::FAILURE;
        }
        $this->info("Supplier mapping saved for {$company->reference} (FlowTracker ID {$id}).");
        return self::SUCCESS;
    }
}
