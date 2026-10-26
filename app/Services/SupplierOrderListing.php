<?php

namespace App\Services;

use App\Models\SupplierResource;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Server-side, tenant-scoped order-list read model. Never exposes buyer contact or sales data. */
final class SupplierOrderListing
{
    public const STAGES = [
        1 => 'New Order',
        2 => 'Artwork',
        3 => 'Production',
        4 => 'QC',
        5 => 'Shipment',
        6 => 'Billing',
        7 => 'Payment',
    ];

    // Supplier-visible order stages; Billing and Payment remain buyer-managed.
    private const SUPPLIER_STAGES = [
        1 => ['name' => 'New Order', 'color' => '#316bc5'],
        2 => ['name' => 'Artwork', 'color' => '#7b61c9'],
        3 => ['name' => 'Production', 'color' => '#d17b1f'],
        4 => ['name' => 'QC', 'color' => '#138d7a'],
        5 => ['name' => 'Shipment', 'color' => '#1873a8'],
    ];

    /** @return array{page: LengthAwarePaginator, rows: Collection, filters: array, stage_cards: array} */
    public function forCompany(int $companyId, Request $request, bool $includeStageCards = true): array
    {
        $valid = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'hold' => ['nullable', 'in:1'],
            'stage' => ['nullable', 'integer', 'between:1,7'],
            'quick' => ['nullable', 'in:today,completed'],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $filters = [
            'search' => trim((string) ($valid['search'] ?? '')),
            'hold' => (string) ($valid['hold'] ?? '') === '1',
            'stage' => (int) ($valid['stage'] ?? 0),
            'quick' => (string) ($valid['quick'] ?? ''),
            'from' => (string) ($valid['from'] ?? ''),
            'to' => (string) ($valid['to'] ?? ''),
        ];
        // The stage and quick-filter cards are mutually exclusive.
        if ($filters['stage'] && $filters['quick'] !== '') $filters['quick'] = ''; // A newly selected stage overrides the quick filter.
        if ($filters['from'] !== '' && $filters['to'] !== '' && $filters['to'] < $filters['from']) {
            abort(422, 'The end date must not be earlier than the start date.');
        }
        $base = SupplierResource::query()
            ->where('supplier_company_id', $companyId)
            ->where('type', 'order')
            ->where('status', '!=', 'revoked');

        $query = clone $base;
        if ($filters['search'] !== '') {
            $needle = '%'.addcslashes(mb_strtolower($filters['search']), '%_\\').'%';
            $query->where(static function ($q) use ($needle): void {
                $q->where('payload->listing->search_text', 'like', $needle)
                    ->orWhere('external_reference', 'like', $needle);
            });
        }
        if ($filters['hold']) $query->whereJsonContains('payload->listing->on_hold', true);
        if ($filters['from'] !== '') {
            $query->where(static function ($q) use ($filters): void {
                $q->where('payload->listing->created_date', '>=', $filters['from'])
                    ->orWhere(static function ($legacy) use ($filters): void {
                        $legacy->whereNull('payload->listing->created_date')
                            ->where('payload->created_at', '>=', $filters['from']);
                    });
            });
        }
        if ($filters['to'] !== '') {
            $query->where(static function ($q) use ($filters): void {
                $q->where('payload->listing->created_date', '<=', $filters['to'])
                    ->orWhere(static function ($legacy) use ($filters): void {
                        $legacy->whereNull('payload->listing->created_date')
                            ->where('payload->created_at', '<=', $filters['to'].'T23:59:59');
                    });
            });
        }
        // Reuse the existing supplier-scoped stage-card scan for all seven counters.
        // Never infer supplier completion from the buyer's overall order status.
        $stageCards = [];
        $legacyIdsForSelectedStage = [];
        $completedIds = [];
        if ($includeStageCards) {
            $stageCounts = array_fill_keys(array_keys(self::SUPPLIER_STAGES), 0);
            $todayCount = 0;
            $completedCount = 0;
            $today = now()->toDateString();
            (clone $query)->select(['id', 'payload'])
                ->chunkById(200, function ($orders) use (&$stageCounts, &$legacyIdsForSelectedStage, &$completedIds, &$todayCount, &$completedCount, $today, $filters): void {
                    foreach ($orders as $order) {
                        $payload = $order->payload ?? [];
                        $sequence = $this->stageSequence($payload);
                        if (isset($stageCounts[$sequence])) $stageCounts[$sequence]++;
                        $createdDate = (string) ($payload['listing']['created_date'] ?? '');
                        if ($createdDate === '') $createdDate = substr((string) ($payload['created_at'] ?? ''), 0, 10);
                        if ($createdDate === $today) $todayCount++;
                        $supplierComplete = $this->supplierWorkCompleted($payload);
                        if ($supplierComplete) {
                            $completedCount++;
                            // IDs are only needed to apply the Completed filter.
                            if ($filters['quick'] === 'completed') $completedIds[] = $order->id;
                        }
                        // Only legacy records need an ID fallback for stage filtering.
                        $projected = (int) ($payload['listing']['stage_sequence'] ?? 0);
                        if ($filters['stage'] === $sequence && !isset(self::STAGES[$projected])) {
                            $legacyIdsForSelectedStage[] = $order->id;
                        }
                    }
                });

            foreach (self::SUPPLIER_STAGES as $sequence => $stage) {
                $stageCards[] = [
                    'kind' => 'stage', 'sequence' => $sequence,
                    'name' => $stage['name'], 'color' => $stage['color'],
                    'count' => $stageCounts[$sequence],
                ];
            }
            $stageCards[] = [
                'kind' => 'quick', 'quick' => 'today', 'kicker' => 'Today',
                'name' => 'Created Today', 'color' => '#316bc5', 'count' => $todayCount,
            ];
            $stageCards[] = [
                'kind' => 'quick', 'quick' => 'completed', 'kicker' => 'Status',
                'name' => 'Completed', 'color' => '#198963', 'count' => $completedCount,
            ];
        }

        if ($filters['stage']) {
            $query->where(function ($stageQuery) use ($filters, $legacyIdsForSelectedStage): void {
                $stageQuery->where('payload->listing->stage_sequence', $filters['stage']);
                if ($legacyIdsForSelectedStage !== []) {
                    $stageQuery->orWhereIn('id', $legacyIdsForSelectedStage);
                }
            });
        }
        if ($filters['quick'] === 'today') {
            $today = now()->toDateString();
            $query->where(static function ($q) use ($today): void {
                $q->where('payload->listing->created_date', $today)
                    ->orWhere(static function ($legacy) use ($today): void {
                        $legacy->whereNull('payload->listing->created_date')
                            ->where('payload->created_at', 'like', $today.'%');
                    });
            });
        } elseif ($filters['quick'] === 'completed') {
            // No extra queries: matched IDs came from the card's scoped scan.
            $query->whereIn('id', $completedIds);
        }
        $page = $query->orderByDesc('id')->paginate(10, [
            'id', 'external_reference', 'status', 'payload', 'due_at', 'created_at',
        ], 'orders_page')->appends(array_filter([
            'view' => 'orders',
            'search' => $filters['search'],
            'hold' => $filters['hold'] ? '1' : '',
            'stage' => $filters['stage'] ?: '',
            'quick' => $filters['quick'],
            'from' => $filters['from'],
            'to' => $filters['to'],
        ], static fn ($value) => $value !== ''));

        return [
            'page' => $page,
            'rows' => $page->getCollection()->map(fn (SupplierResource $order): array => $this->row($order)),
            'filters' => $filters,
            'stage_cards' => $stageCards,
        ];
    }

    /** Completion is supplier-side: at least one assigned required task, all done.
     *  Buyer-only stages and the overall FlowTracker order status are excluded. */
    private function supplierWorkCompleted(array $payload): bool
    {
        $assignedCount = 0;
        $stages = $payload['workflow']['stages'] ?? [];
        foreach (is_array($stages) ? $stages : [] as $stage) {
            foreach (is_array($stage['tasks'] ?? null) ? $stage['tasks'] : [] as $task) {
                if (empty($task['assigned_to_supplier']) || empty($task['required'])) continue;
                $assignedCount++;
                if (($task['mode'] ?? '') !== 'done'
                    && strcasecmp(trim((string) ($task['status'] ?? '')), 'Completed') !== 0) {
                    return false;
                }
            }
        }
        return $assignedCount > 0;
    }

    /** Match the table's stage display for both current and legacy order payloads. */
    private function stageSequence(array $payload): int
    {
        $sequence = (int) ($payload['listing']['stage_sequence'] ?? 0);
        if (isset(self::STAGES[$sequence])) return $sequence;

        $workflow = $payload['workflow'] ?? [];
        $stages = $workflow['stages'] ?? [];
        foreach (is_array($stages) ? $stages : [] as $stage) {
            if ((int) ($stage['id'] ?? 0) === (int) ($workflow['current_phase_id'] ?? -1)) {
                $sequence = (int) ($stage['sequence'] ?? 0);
                return isset(self::STAGES[$sequence]) ? $sequence : 1;
            }
        }
        return 1;
    }

    private function row(SupplierResource $order): array
    {
        $payload = $order->payload ?? [];
        $list = $payload['listing'] ?? [];
        $workflow = $payload['workflow'] ?? [];
        $stages = $workflow['stages'] ?? [];
        $active = collect(is_array($stages) ? $stages : [])->first(
            static fn ($stage) => (int) ($stage['id'] ?? 0) === (int) ($workflow['current_phase_id'] ?? -1)
        );
        $sequence = $this->stageSequence($payload);
        $progress = $list['progress'] ?? null;
        $color = (string) (($list['stage_color'] ?? '') ?: ($active['color'] ?? '#0a8f7a'));
        if (! preg_match('/^#[0-9a-f]{6}$/i', $color)) $color = '#0a8f7a';
        $created = $payload['created_at'] ?? $order->created_at;
        $createdLabel = '-';
        try {
            $createdDate = $created ? Carbon::parse($created) : null;
            $created = $createdDate?->format('Y-m-d') ?? '';
            $createdLabel = $createdDate?->format('M j, Y') ?? '-';
        } catch (\Throwable) {
            $created = '';
        }
        $products = collect($payload['items'] ?? [])->pluck('product_name')->filter()->values();
        $status = trim((string) ($payload['order_status'] ?? 'In Progress')) ?: 'In Progress';
        return [
            'id' => $order->id,
            'reference' => $order->external_reference,
            'created' => $created,
            'created_label' => $createdLabel,
            'item_count' => count($payload['items'] ?? []),
            'products' => $products->take(2)->join(' · ').($products->count() > 2 ? ' · +'.($products->count() - 2).' more' : ''),
            'stage' => self::STAGES[$sequence],
            'stage_color' => $color,
            'status' => $status,
            'hold' => (bool) ($list['on_hold'] ?? false),
            // Only FlowTracker's buyer-set supplier date, never customer ETA or due_at.
            'supplier_delivery' => ! empty($payload['supplier_ship_date'])
                ? Carbon::parse($payload['supplier_ship_date'])->format('M j, Y') : null,
            'progress' => $progress === null ? null : max(0, min(100, (int) $progress)),
        ];
    }
}
