@php($product = \App\Services\SupplierDashboardData::class)
<div class="dashboard-hero">
    <div>
        <div class="workspace-pill"><span class="sync-dot" style="box-shadow:none;background:#83e5d2"></span> Supplier workspace</div>
        <h1>Welcome back, {{ $user->name }}</h1>
        <p>You have <strong style="color:#fff">{{ $rfqActionCount }} RFQs requiring attention</strong> and {{ $activeOrderCount }} active orders. Your assigned work appears below, prioritized by deadline.</p>
        <div class="dashboard-hero-actions">
            @if($priorityRfqs->isNotEmpty())
                <button class="btn btn-primary btn-sm" type="button" data-open-rfq="{{ $priorityRfqs->first()->id }}">Review next action <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button>
                <button class="btn btn-secondary-hero btn-sm" type="button" data-open-quotations>View all RFQs</button>
            @else
                <button class="btn btn-primary btn-sm" type="button" data-open-quotations>View all RFQs</button>
            @endif
        </div>
    </div>
    <div class="hero-summary">
        <div class="hero-summary-head"><div><small>NEXT RFQ DEADLINE</small><strong>{{ $nextDeadline?->due_at?->format('M j, Y') ?? 'No upcoming deadline' }}</strong></div>@if($nextDeadline)<span class="time-chip">{{ $nextDeadline->due_at->format('M j') }}</span>@endif</div>
        <div class="hero-summary-product">
            <div class="product-thumb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M5 9c4-2 10-2 14 0v7c-4 2-10 2-14 0V9Z"/><path d="M8 11h8M8 14h8"/></svg></div>
            <div><strong>{{ $nextDeadline ? $product::product($nextDeadline) : 'No pending RFQ deadlines' }}</strong><span>{{ $nextDeadline?->external_reference ?? 'You are up to date' }}</span></div>
        </div>
        <div class="hero-summary-foot"><span>Supplier workspace</span><span>{{ $nextDeadline ? 'Awaiting quotation' : 'No deadlines pending' }}</span></div>
    </div>
</div>
<div class="stats supplier-dashboard-stats">
    <div class="stat-card"><div class="stat-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3h8l4 4v14H7z"/><path d="M15 3v5h5"/></svg></div><div class="stat-text"><small>RFQs needing action</small><strong>{{ $rfqActionCount }}</strong><span>Open or draft quotations</span></div><span class="stat-chip attn">{{ $rfqActionCount }} action{{ $rfqActionCount === 1 ? '' : 's' }}</span></div>
    <div class="stat-card"><div class="stat-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v13H4z"/><path d="M8 7V4h8v3M8 11h8"/></svg></div><div class="stat-text"><small>Active orders</small><strong>{{ $activeOrderCount }}</strong><span>Orders shared by FlowTrack</span></div></div>
    <div class="stat-card"><div class="stat-icon"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 12l4 4L19 6"/></svg></div><div class="stat-text"><small>Submitted this month</small><strong>{{ $submittedMonthCount }}</strong><span>Submitted quotations</span></div></div>
</div>
<div class="dashboard-grid">
    <div class="card">
        <div class="card-header"><div><h2>RFQs requiring action</h2><p>Prioritized by deadline</p></div><button class="btn btn-ghost btn-sm" type="button" data-open-quotations>View all</button></div>
        <div class="card-body priority-list" id="priorityList">
            @forelse($priorityRfqs as $rfq)
                <div class="priority-item {{ $rfq->due_at?->isPast() ? 'urgent' : 'open' }}">
                    <div class="priority-main"><div class="product-thumb">RFQ</div><div><strong class="item-title">{{ $product::product($rfq) }}</strong><div class="item-ref">{{ $rfq->external_reference }}</div><div class="item-meta">{{ $rfq->due_at ? ($rfq->due_at->isPast() ? 'Overdue' : 'Due').' '.$rfq->due_at->format('M j, Y') : 'No deadline' }} · {{ ucfirst($rfq->status) }}</div></div></div>
                    <div class="priority-actions"><button type="button" class="btn btn-primary btn-sm" data-open-rfq="{{ $rfq->id }}">{{ $rfq->status === 'draft' ? 'Continue draft' : 'Review request' }}</button></div>
                </div>
            @empty
                <div class="empty-state" style="padding:20px">No RFQs require action right now.</div>
            @endforelse
        </div>
    </div>
    <div style="display:grid;gap:16px;align-content:start">
        <div class="card">
            <div class="card-header"><div><h2>Upcoming deadlines</h2><p>Upcoming quotation due dates</p></div></div>
            <div class="card-body deadline-list">
                @forelse($upcomingDeadlines as $rfq)
                    <button class="status-line supplier-dashboard-link" type="button" data-open-rfq="{{ $rfq->id }}"><span><strong>{{ $rfq->external_reference }}</strong><span>{{ $product::product($rfq) }}</span></span><strong>{{ $rfq->due_at->format('M j') }}</strong></button>
                @empty
                    <div class="empty-state"><p>No upcoming RFQ deadlines.</p></div>
                @endforelse
            </div>
        </div>
        <div class="card">
            <div class="card-header"><div><h2>Recent activity</h2><p>Latest RFQ and order changes</p></div></div>
            <div class="card-body recent-list supplier-activity-list">
                <?php if ($recentActivity->isEmpty()) { ?>
                    <div class="empty-state"><p>No RFQ or order activity received yet.</p></div>
                <?php } else { ?>
                    <?php foreach ($recentActivity as $entry) {
                        $isRfq = $entry->type === 'rfq';
                        $activityStatus = $isRfq
                            ? ucfirst((string) $entry->status)
                            : (string) data_get($entry->payload, 'order_status', 'Order updated');
                    ?>
                        <?php if ($isRfq) { ?>
                            <button type="button" class="supplier-activity-entry" data-open-rfq="<?= e($entry->id) ?>">
                        <?php } else { ?>
                            <a class="supplier-activity-entry" href="<?= e($product::link($entry)) ?>">
                        <?php } ?>
                            <span class="supplier-activity-icon <?= e($isRfq ? 'supplier-activity-icon-rfq' : '') ?>" aria-hidden="true">
                                <?php if ($isRfq) { ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3h8l4 4v14H7z"/><path d="M15 3v5h5M10 13h6M10 17h6"/></svg>
                                <?php } else { ?>
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v13H4zM8 7V4h8v3M8 11h8"/></svg>
                                <?php } ?>
                            </span>
                            <span class="supplier-activity-content">
                                <span class="supplier-activity-heading"><strong><?= e($entry->external_reference) ?></strong><span class="supplier-activity-state"><?= e($activityStatus) ?></span></span>
                                <span class="supplier-activity-description"><?= e($product::product($entry)) ?></span>
                                <span class="supplier-activity-meta"><span><?= e($isRfq ? 'RFQ' : 'Order') ?></span><span aria-hidden="true">·</span><time datetime="<?= e($entry->updated_at?->toIso8601String()) ?>"><?= e($entry->updated_at?->diffForHumans()) ?></time></span>
                            </span>
                            <svg class="supplier-activity-arrow" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                        <?php if ($isRfq) { ?>
                            </button>
                        <?php } else { ?>
                            </a>
                        <?php } ?>
                    <?php } ?>
                <?php } ?>
            </div>
        </div>
    </div>
</div>

{{-- Supplier-origin order task actions, separate from synchronized order/RFQ changes. --}}
<section class="card supplier-order-actions" aria-labelledby="supplierOrderActivityHeading">
    <div class="card-header">
        <div>
            <h2 id="supplierOrderActivityHeading">Supplier order activity</h2>
            <p>Recent order task actions confirmed by FlowTracker</p>
        </div>
        <span class="supplier-order-actions-source">Supplier actions</span>
    </div>
    <div class="supplier-order-actions-list">
        <?php if ($supplierOrderActions->isEmpty()) { ?>
            <div class="empty-state supplier-order-actions-empty">
                <p>No supplier order actions recorded yet. Confirmed task submissions will appear here.</p>
            </div>
        <?php } else { ?>
            <?php foreach ($supplierOrderActions as $action) { ?>
                <a class="supplier-order-actions-item" href="<?= e($action['url']) ?>">
                    <span class="supplier-order-actions-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v13H4zM8 7V4h8v3M8 11h8"/><path d="m16 16 2 2 3-4"/></svg>
                    </span>
                    <span class="supplier-order-actions-content">
                        <span class="supplier-order-actions-heading">
                            <strong><?= e($action['reference']) ?></strong>
                            <span class="supplier-order-actions-badge"><?= e($action['status']) ?></span>
                        </span>
                        <span class="supplier-order-actions-task"><?= e($action['task']) ?></span>
                        <span class="supplier-order-actions-info"><?= e($action['stage']) ?> · <?= e($action['action']) ?> · <?= e($action['actor']) ?></span>
                    </span>
                    <span class="supplier-order-actions-time"><time datetime="<?= e($action['datetime']) ?>"><?= e($action['time']) ?></time><span aria-hidden="true">→</span></span>
                </a>
            <?php } ?>
        <?php } ?>
    </div>
</section>
