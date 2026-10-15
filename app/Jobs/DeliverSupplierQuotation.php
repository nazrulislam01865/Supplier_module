<?php
namespace App\Jobs;
use App\Services\FlowtrackQuotationDelivery;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
/** The quotation itself is already durable; scheduler retries network failures. */
final class DeliverSupplierQuotation implements ShouldQueue {
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;
    public int $tries = 1;
    public int $timeout = 900; // A quotation may have up to ten large attachments.
    public function __construct(public readonly int $quotationId) {}
    public function handle(FlowtrackQuotationDelivery $delivery): void { $delivery->send($this->quotationId); }
}
