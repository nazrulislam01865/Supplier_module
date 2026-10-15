@php
    $notificationParams = ['view' => 'notifications', 'notification_type' => $notificationType, 'notification_state' => $notificationState];
@endphp
<div class="card supplier-notification-card">
    <div class="supplier-notification-toolbar">
        <nav class="supplier-notification-filters" aria-label="Notification status">
            <a href="{{ route('supplier.home', array_merge($notificationParams, ['notification_state' => 'all'])) }}" @class(['supplier-notification-filter', 'is-active' => $notificationState === 'all'])
               @if($notificationState === 'all') aria-current="page" @endif
            >All updates</a>
            <a href="{{ route('supplier.home', array_merge($notificationParams, ['notification_state' => 'unread'])) }}" @class(['supplier-notification-filter', 'is-active' => $notificationState === 'unread'])
               @if($notificationState === 'unread') aria-current="page" @endif
            >Unread</a>
        </nav>
        <div class="supplier-notification-type-wrap">
            <label for="notificationTypeSelect">Show</label>
            <select id="notificationTypeSelect" class="supplier-notification-type-select" aria-label="Notification type">
                <option value="{{ route('supplier.home', array_merge($notificationParams, ['notification_type' => 'all'])) }}" @selected($notificationType === 'all')>All types</option>
                <option value="{{ route('supplier.home', array_merge($notificationParams, ['notification_type' => 'order'])) }}" @selected($notificationType === 'order')>Orders</option>
                <option value="{{ route('supplier.home', array_merge($notificationParams, ['notification_type' => 'rfq'])) }}" @selected($notificationType === 'rfq')>RFQs</option>
            </select>
        </div>
    </div>
    <div class="supplier-notification-count" role="status">
        @if($notificationPage->total())
            Showing {{ $notificationPage->firstItem() }}–{{ $notificationPage->lastItem() }} of {{ number_format($notificationPage->total()) }} updates
        @else
            No {{ $notificationState === 'unread' ? 'unread ' : '' }}{{ $notificationType === 'all' ? '' : ($notificationType === 'rfq' ? 'RFQ ' : 'order ') }}updates
        @endif
    </div>
    <div class="supplier-notification-items">
        @forelse($notificationPage as $notice)
            @php
                $isUnread = ! $user->notifications_read_at || $notice->updated_at->gt($user->notifications_read_at);
                $isRfq = $notice->type === 'rfq';
            @endphp
            @if($isRfq)
                <button type="button" class="supplier-notification-row supplier-notice-link {{ $isUnread ? 'supplier-notice-new' : '' }}" data-open-rfq="{{ $notice->id }}">
            @else
                <a class="supplier-notification-row supplier-notice-link {{ $isUnread ? 'supplier-notice-new' : '' }}" href="{{ \App\Services\SupplierDashboardData::link($notice) }}">
            @endif
                <span class="supplier-notification-symbol {{ $isRfq ? 'is-rfq' : 'is-order' }}" aria-hidden="true">
                    @if($isRfq)
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 5h16v14H4zM8 9h8M8 13h5M8 16h4"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v13H4zM8 7V4h8v3M8 11h8"/></svg>
                    @endif
                </span>
                <span class="supplier-notification-copy">
                    <span class="supplier-notification-primary">
                        <strong>{{ $notice->external_reference }}</strong>
                        <span class="supplier-notification-tag">{{ $isRfq ? 'RFQ' : 'Order' }}</span>
                        @if($isUnread)
                            <span class="supplier-notification-unread-label">New</span>
                        @endif
                    </span>
                    <span class="supplier-notification-title">{{ \App\Services\SupplierDashboardData::product($notice) }}</span>
                    <span class="supplier-notification-meta">
                        <span>{{ $isRfq ? ucfirst($notice->status) : data_get($notice->payload, 'order_status', 'Order updated') }}</span>
                        <time datetime="{{ $notice->updated_at->toIso8601String() }}" title="{{ $notice->updated_at->format('M j, Y g:i A') }}">{{ $notice->updated_at->diffForHumans() }}</time>
                    </span>
                </span>
                <span class="supplier-notification-open" aria-hidden="true">View <span>→</span></span>
            @if($isRfq)
                </button>
            @else
                </a>
            @endif
        @empty
            <div class="supplier-notification-empty">
                <span class="supplier-notification-empty-icon" aria-hidden="true">✓</span>
                <h3>{{ $notificationState === 'unread' ? 'You’re all caught up' : 'No updates yet' }}</h3>
                <p>{{ $notificationState === 'unread' ? 'There are no unread updates matching these filters.' : 'Order and RFQ updates will appear here when they are available.' }}</p>
                @if($notificationState !== 'all' || $notificationType !== 'all')
                    <a class="btn btn-outline-teal btn-sm" href="{{ route('supplier.home', ['view' => 'notifications']) }}">View all updates</a>
                @endif
            </div>
        @endforelse
    </div>
    @if($notificationPage->hasPages())
        <div class="supplier-notification-pagination">
            <span>Page {{ $notificationPage->currentPage() }} of {{ $notificationPage->lastPage() }}</span>
            {{ $notificationPage->onEachSide(1)->links('portal.partials.notification-pagination') }}
        </div>
    @endif
</div>
