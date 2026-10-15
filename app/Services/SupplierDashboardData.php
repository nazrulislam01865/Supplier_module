<?php

namespace App\Services;

use App\Models\SupplierQuotation;
use App\Models\SupplierResource;
use App\Models\SupplierUser;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/** Dashboard projections from the local, signed, supplier-scoped bridge records. */
final class SupplierDashboardData
{
    public function forUser(SupplierUser $user): array
    {
        $companyId = (int) $user->supplier_company_id;
        $base = SupplierResource::query()->where('supplier_company_id', $companyId);
        $actionable = (clone $base)->where('type', 'rfq')->whereIn('status', ['open', 'draft'])
            ->where(fn ($query) => $query->whereNull('due_at')->orWhere('due_at', '>=', now()));
        $priority = (clone $actionable)->orderByRaw('CASE WHEN due_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('due_at')->limit(5)->get(['id', 'external_reference', 'status', 'payload', 'due_at']);
        $deadlines = $priority->filter(fn (SupplierResource $row) => $row->due_at !== null)->values();
        $next = $deadlines->first();
        $notifications = (clone $base)->whereIn('type', ['rfq', 'order'])
            ->where('status', '!=', 'revoked')->latest('updated_at')->latest('id')->limit(20)
            ->get(['id', 'type', 'external_reference', 'status', 'payload', 'updated_at']);
        // Locally confirmed supplier task submissions; never infer supplier
        // actions from externally synchronized order status changes.
        $supplierOrderActions = DB::table('supplier_order_activities as activity')
            ->join('supplier_resources as order', function ($join) {
                $join->on('order.id', '=', 'activity.supplier_resource_id')
                    ->on('order.supplier_company_id', '=', 'activity.supplier_company_id');
            })
            ->where('activity.supplier_company_id', $companyId)
            ->where('order.type', 'order')
            ->where('order.status', '!=', 'revoked')
            ->orderByDesc('activity.created_at')
            ->orderByDesc('activity.id')
            ->limit(6)
            ->get([
                'activity.supplier_resource_id', 'order.external_reference',
                'activity.actor_name', 'activity.stage_name', 'activity.task_title',
                'activity.decision', 'activity.task_status', 'activity.created_at',
            ])->map(function ($row): array {
                $date = Carbon::parse($row->created_at);
                return [
                    'reference' => $row->external_reference,
                    'url' => route('supplier.orders.show', $row->supplier_resource_id),
                    'actor' => $row->actor_name,
                    'stage' => $row->stage_name,
                    'task' => $row->task_title,
                    'action' => ucwords(str_replace('_', ' ', $row->decision)),
                    'status' => $row->task_status,
                    'time' => $date->diffForHumans(),
                    'datetime' => $date->toIso8601String(),
                ];
            });
        $readAt = $user->notifications_read_at;
        $unread = (clone $base)->whereIn('type', ['rfq', 'order'])
            ->where('status', '!=', 'revoked')
            ->when($readAt, fn ($query) => $query->where('updated_at', '>', $readAt))
            ->count();

        return [
            'rfqActionCount' => (clone $actionable)->count(),
            'activeOrderCount' => (clone $base)->where('type', 'order')->where('status', '!=', 'revoked')->count(),
            'submittedMonthCount' => SupplierQuotation::query()->where('supplier_company_id', $companyId)
                ->where('status', 'submitted')->where('submitted_at', '>=', now()->startOfMonth())
                ->where('submitted_at', '<', now()->startOfMonth()->addMonth())->count(),
            'nextDeadline' => $next,
            'priorityRfqs' => $priority,
            'upcomingDeadlines' => $deadlines,
            'recentActivity' => $notifications->take(6),
            'supplierOrderActions' => $supplierOrderActions,
            'notifications' => $notifications,
            'unreadNotifications' => $unread,
        ];
    }

    /** The notification center is paginated independently of the 20-item bell preview. */
    public function notificationPage(SupplierUser $user, string $type = 'all', string $state = 'all'): LengthAwarePaginator
    {
        $query = SupplierResource::query()
            ->where('supplier_company_id', $user->supplier_company_id)
            ->whereIn('type', ['rfq', 'order'])
            ->where('status', '!=', 'revoked');

        if (in_array($type, ['rfq', 'order'], true)) {
            $query->where('type', $type);
        }
        if ($state === 'unread' && $user->notifications_read_at) {
            $query->where('updated_at', '>', $user->notifications_read_at);
        }

        return $query->orderByDesc('updated_at')->orderByDesc('id')
            ->paginate(15, ['id', 'type', 'external_reference', 'status', 'payload', 'updated_at'], 'notifications_page')
            ->withPath(route('supplier.home'))
            ->appends(['view' => 'notifications', 'notification_type' => $type, 'notification_state' => $state]);
    }

    /** Lightweight unread indicator for standalone supplier pages. */
    public function hasUnreadNotifications(SupplierUser $user): bool
    {
        return SupplierResource::query()
            ->where('supplier_company_id', $user->supplier_company_id)
            ->whereIn('type', ['rfq', 'order'])
            ->where('status', '!=', 'revoked')
            ->when($user->notifications_read_at, fn ($query) => $query->where('updated_at', '>', $user->notifications_read_at))
            ->exists();
    }

    public static function product(SupplierResource $resource): string
    {
        $payload = $resource->payload ?? [];
        return (string) ($payload['product_name'] ?? $payload['title'] ?? ('Order '.$resource->external_reference));
    }

    public static function link(SupplierResource $resource): string
    {
        if ($resource->type === 'order') return route('supplier.orders.show', $resource->id);
        return route('supplier.home', ['view' => 'quotations', 'rfq' => $resource->id]);
    }
}
