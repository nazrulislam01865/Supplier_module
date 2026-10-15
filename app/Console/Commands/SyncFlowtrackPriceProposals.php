<?php
namespace App\Console\Commands;

use App\Models\SupplierPriceProposal;
use App\Services\FlowtrackPriceProposalDelivery;
use Illuminate\Console\Command;

final class SyncFlowtrackPriceProposals extends Command
{
    protected $signature = 'supplier:sync-price-proposals';
    protected $description = 'Retry pending signed supplier price requests';

    public function handle(FlowtrackPriceProposalDelivery $delivery): int
    {
        $count = 0;
        SupplierPriceProposal::query()->where('status', 'pending')->where('delivery_status', 'pending')
            ->where(fn ($q) => $q->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))
            ->orderBy('id')->limit(100)->pluck('id')->each(function ($id) use ($delivery, &$count): void {
                if ($delivery->send((int) $id)) $count++;
            });
        $this->info("Price proposals delivered: {$count}.");
        return self::SUCCESS;
    }
}
