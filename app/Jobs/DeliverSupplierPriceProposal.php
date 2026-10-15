<?php
namespace App\Jobs;
use App\Services\FlowtrackPriceProposalDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
final class DeliverSupplierPriceProposal implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public function __construct(public readonly int $proposalId) {}
    public function handle(FlowtrackPriceProposalDelivery $delivery): void { $delivery->send($this->proposalId); }
}
