<?php

namespace App\Services;

use App\Models\SupplierResource;
use App\Models\SupplierUser;

/** Small, single-query sidebar projection for standalone supplier pages. */
final class SupplierSidebarCounts
{
    /** @return array{rfqs: int, orders: int, unread: int} */
    public function forUser(SupplierUser $user): array
    {
        $readAt = $user->notifications_read_at;
        $unreadCondition = $readAt !== null ? ' AND updated_at > ?' : '';
        $bindings = ['rfq', 'open', 'draft', 'order', 'revoked', 'rfq', 'order', 'revoked'];
        if ($readAt !== null) {
            $bindings[] = $readAt;
        }

        // The supplier-company scope is mandatory. Aggregate all three counts
        // in one query; no dashboard data or full resource records are loaded.
        $counts = SupplierResource::query()
            ->where('supplier_company_id', $user->supplier_company_id)
            ->whereIn('type', ['rfq', 'order'])
            ->selectRaw("
                SUM(CASE WHEN type = ? AND status IN (?, ?) THEN 1 ELSE 0 END) AS rfqs,
                SUM(CASE WHEN type = ? AND status != ? THEN 1 ELSE 0 END) AS orders,
                SUM(CASE WHEN type IN (?, ?) AND status != ?{$unreadCondition} THEN 1 ELSE 0 END) AS unread
            ", $bindings)
            ->first();

        return [
            'rfqs' => (int) ($counts?->rfqs ?? 0),
            'orders' => (int) ($counts?->orders ?? 0),
            'unread' => (int) ($counts?->unread ?? 0),
        ];
    }
}
