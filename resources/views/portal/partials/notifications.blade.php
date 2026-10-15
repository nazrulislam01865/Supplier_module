@php($product = \App\Services\SupplierDashboardData::class)
@forelse($notifications as $notice)
    @php($new = ! $user->notifications_read_at || $notice->updated_at->gt($user->notifications_read_at))
    @php($isRfq = $notice->type === 'rfq')
    @if($isRfq)
        <button type="button" class="notice supplier-notice-link {{ $new ? 'supplier-notice-new' : '' }}" data-notice-type="rfq" data-notice-unread="{{ $new ? '1' : '0' }}" data-open-rfq="{{ $notice->id }}">
    @else
        <a class="notice supplier-notice-link {{ $new ? 'supplier-notice-new' : '' }}" data-notice-type="order" data-notice-unread="{{ $new ? '1' : '0' }}" href="{{ $product::link($notice) }}">
    @endif
        <span class="notice-icon {{ $isRfq ? 'notice-icon-rfq' : 'notice-icon-order' }}" aria-hidden="true">
            @if($isRfq)
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M5 3.5h10l4 4V20H5zM15 3.5V8h4M8 12h8M8 16h6"/></svg>
            @else
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3.5 7.5 12 3l8.5 4.5v9L12 21l-8.5-4.5zM3.5 7.5 12 12l8.5-4.5M12 12v9"/></svg>
            @endif
        </span>
        <span class="notice-content">
            <span class="notice-heading">
                <strong>{{ $notice->external_reference }}</strong>
                @if($new)<span class="notice-unread-dot" aria-label="Unread notification"></span>@endif
            </span>
            <span class="notice-product">{{ $product::product($notice) }}</span>
            <span class="notice-meta">
                <span class="notice-status">{{ $isRfq ? ucfirst($notice->status) : data_get($notice->payload, 'order_status', 'Order updated') }}</span>
                <time datetime="{{ $notice->updated_at->toIso8601String() }}" title="{{ $notice->updated_at->format('M j, Y g:i A') }}">{{ $notice->updated_at->diffForHumans() }}</time>
            </span>
        </span>
        <span class="notice-arrow" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 5 7 7-7 7"/></svg></span>
    @if($isRfq)
        </button>
    @else
        </a>
    @endif
@empty
    <div class="notice-empty-base">
        <span class="notice-empty-symbol" aria-hidden="true">✓</span>
        <strong>No updates yet</strong>
        <p>RFQ and order activity will appear here.</p>
    </div>
@endforelse
