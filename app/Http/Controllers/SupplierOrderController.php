<?php

namespace App\Http\Controllers;

use App\Services\SupplierScope;
use App\Services\SupplierDashboardData;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;

/** Display only the authenticated supplier's signed, stored FlowTracker projection. */
final class SupplierOrderController extends Controller
{
    public function show(Request $request, int $id, SupplierScope $scope, SupplierDashboardData $dashboardData)
    {
        $order = $scope->resource($request, $id, 'order');
        abort_if($order->status === 'revoked', 404);

        $details = $order->payload ?? [];
        $currency = (string) ($details['currency'] ?? 'USD');
        $items = collect($details['items'] ?? [])->map(function (array $item) use ($currency): array {
            $quantity = (int) ($item['quantity'] ?? 0);
            $unitPrice = isset($item['unit_price']) && is_numeric($item['unit_price'])
                ? (float) $item['unit_price'] : null;
            $totalPrice = $unitPrice !== null ? $unitPrice * $quantity : null;

            return [
                'product_name' => (string) ($item['product_name'] ?? 'Product'),
                'quantity_label' => number_format($quantity).' units',
                'unit_price_label' => $this->money($unitPrice, $currency),
                'total_price_label' => $this->money($totalPrice, $currency),
                'notes' => (string) ($item['notes'] ?? ''),
            ];
        })->values();
        $documents = array_values($details['documents'] ?? []);
        $stages = $this->mapStages($details['stages'] ?? [], $documents);

        return view('portal.order.show', [
            'order' => $order,
            'summary' => [
                'breadcrumb_reference' => (string) $order->external_reference,
                'title' => (string) ($details['title'] ?? ('Order '.$order->external_reference)),
            ],
            'items' => $items,
            'stages' => $stages,
            'workflow' => $details['workflow'] ?? [],
            // Source events only. Do not reconstruct fictional status history.
            'recentUpdates' => collect($details['recent_updates'] ?? [])->take(9)->map(fn ($event) => [
                'title' => $event['title'],
                'status' => $event['status'],
                'date' => $this->date($event['date'] ?? null, true),
                'description' => '',
            ])->all(),
            'approvedArtwork' => $documents[0] ?? null,
            'approvedArtworks' => $documents,
            'company' => $request->user()->company,
            'user' => $request->user(),
            'hasUnreadNotifications' => $dashboardData->hasUnreadNotifications($request->user()),
        ]);
    }

    private function mapStages(array $input, array $documents): array
    {
        $defaults = [
            'artwork' => ['name' => 'Artwork', 'helper' => 'Will start when files are received'],
            'production' => ['name' => 'Production', 'helper' => 'Will start after artwork'],
            'quality_control' => ['name' => 'Quality Control', 'helper' => 'Will start after production'],
        ];
        $byName = [];
        foreach ($input as $stage) {
            $key = match (strtolower(trim((string) ($stage['name'] ?? '')))) {
                'artwork' => 'artwork', 'production' => 'production',
                'quality control', 'quality_control' => 'quality_control', default => null,
            };
            if ($key !== null) $byName[$key] = $stage;
        }
        $result = [];
        foreach ($defaults as $key => $base) {
            $source = $byName[$key] ?? [];
            $state = in_array($source['status'] ?? '', ['completed', 'in_progress'], true)
                ? $source['status'] : 'upcoming';
            $tasks = [];
            foreach ($source['tasks'] ?? [] as $index => $task) {
                $taskStatus = in_array($task['status'] ?? '', ['completed', 'skipped', 'in_progress', 'pending'], true)
                    ? $task['status'] : 'upcoming';
                $tasks[] = [
                    'index' => $index + 1,
                    'name' => $task['name'],
                    'status' => $taskStatus,
                    'status_label' => match ($taskStatus) {
                        'completed' => 'Completed', 'skipped' => 'Skipped',
                        'in_progress' => 'In Progress', 'pending' => 'Pending', default => 'Upcoming',
                    },
                    'date_label' => $this->date($task['updated_at'] ?? null),
                ];
            }
            $result[] = [
                'key' => $key,
                'name' => $base['name'],
                'status' => $state,
                'status_label' => match ($state) {
                    'completed' => 'Completed', 'in_progress' => 'In Progress', default => 'Upcoming',
                },
                'helper' => match ($state) {
                    'completed' => $base['name'].' completed',
                    'in_progress' => $base['name'].' in progress',
                    default => $base['helper'],
                },
                'headline_date' => $this->date($source['updated_at'] ?? null),
                'tasks' => $tasks,
                'document' => $key === 'artwork' ? ($documents[0] ?? null) : null,
            ];
        }
        return $result;
    }

    private function money(?float $amount, string $currency): string
    {
        if ($amount === null) return '—';
        $symbol = match ($currency) {
            'USD' => '$', 'GBP' => '£', 'EUR' => '€', default => $currency.' ',
        };
        return $symbol.number_format($amount, 2);
    }

    private function date(mixed $value, bool $time = false): string
    {
        if (! $value) return '—';
        try {
            return Carbon::parse($value)->format($time ? 'M j, Y g:i A' : 'M j, Y');
        } catch (\Throwable) {
            return '—';
        }
    }
}
