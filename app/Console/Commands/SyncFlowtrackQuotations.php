<?php

namespace App\Console\Commands;

use App\Models\SupplierQuotation;
use App\Services\FlowtrackQuotationDelivery;
use Illuminate\Console\Command;
use RuntimeException;

final class SyncFlowtrackQuotations extends Command
{
    protected $signature = 'supplier:sync-quotations {--limit=25} {--id= : Sync one existing quotation ID} {--retry-now : Ignore retry delay for that quotation only}';
    protected $description = 'Deliver submitted quotations and private files to FlowTracker';

    public function handle(FlowtrackQuotationDelivery $delivery): int
    {
        $idOption = $this->option('id');
        $id = $idOption !== null && $idOption !== '' ? filter_var($idOption, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) : null;
        if (($idOption !== null && $idOption !== '' && $id === false) || ($this->option('retry-now') && !$id)) {
            $this->error('Use --id=<positive quotation ID> with --retry-now.');
            return self::FAILURE;
        }

        $retryNow = (bool) $this->option('retry-now');
        $query = SupplierQuotation::query()->where('status', 'submitted');
        if ($id) {
            $query->whereKey($id);
        }
        $ids = $query->where(fn ($q) => $q->where(fn ($pending) => $pending->where('sync_status', 'pending')
                ->when(!$retryNow, fn ($eligible) => $eligible->where(fn ($time) => $time->whereNull('sync_next_attempt_at')->orWhere('sync_next_attempt_at', '<=', now()))))
            ->orWhere(fn ($stale) => $stale->where('sync_status', 'sending')->where('sync_locked_at', '<', now()->subMinutes(30))))
            ->orderBy('id')->limit($id ? 1 : max(1, min(100, (int) $this->option('limit'))))->pluck('id');

        if ($ids->isEmpty()) {
            $this->info($id ? 'That quotation is already delivered, not submitted, locked by an active transfer, or waiting for its retry time.' : 'No quotation deliveries are eligible right now.');
            return self::SUCCESS;
        }

        try {
            $delivery->assertConfigured();
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());
            return self::FAILURE;
        }

        $sent = 0;
        foreach ($ids as $quoteId) {
            if ($delivery->send((int) $quoteId, $retryNow)) {
                ++$sent;
                continue;
            }
            $record = SupplierQuotation::query()->whereKey($quoteId)->first(['sync_error', 'sync_next_attempt_at']);
            $this->warn('Quotation '.$quoteId.' not delivered: '.($record?->sync_error ?: 'Check the application log.')
                .($record?->sync_next_attempt_at ? ' (retry after '.$record->sync_next_attempt_at.')' : ''));
        }
        $this->info('Supplier quotation deliveries: '.$sent.' sent, '.($ids->count() - $sent).' not delivered.');
        return $sent === $ids->count() ? self::SUCCESS : self::FAILURE;
    }
}
