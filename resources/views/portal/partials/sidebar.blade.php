@php
    // The same sidebar is used by the SPA workspace and standalone order details.
    $isOrderDetail = $isOrderDetail ?? false;
    $isProductPrices = $isProductPrices ?? request()->routeIs('supplier.products.prices*');
    $isStandalone = $isOrderDetail || $isProductPrices;
    $initialView = $initialView ?? 'dashboard';
    $unreadCount = $isStandalone ? ($sidebarCounts['unread'] ?? 0) : ($dashboard['unreadNotifications'] ?? 0);
@endphp
<aside class="sidebar" id="sidebar">
    <div class="nav-section-label">Workspace</div>
    <nav class="nav" aria-label="Supplier workspace">
        @if($isStandalone)
            <a class="nav-item" href="{{ route('supplier.home') }}?view=dashboard">
        @else
            <button class="nav-item {{ $initialView === 'dashboard' ? 'active' : '' }}" data-view="dashboard" type="button">
        @endif
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="3" width="7" height="7" rx="1"/><rect x="14" y="3" width="7" height="7" rx="1"/><rect x="3" y="14" width="7" height="7" rx="1"/><rect x="14" y="14" width="7" height="7" rx="1"/></svg>
            Dashboard
        @if($isStandalone)</a>@else</button>@endif

        @if($isStandalone)
            <a class="nav-item" href="{{ route('supplier.home') }}?view=quotations">
        @else
            <button class="nav-item {{ $initialView === 'quotations' ? 'active' : '' }}" data-view="quotations" type="button">
        @endif
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M7 3h8l4 4v14H7z"/><path d="M15 3v5h5M10 13h6M10 17h6M10 9h2"/></svg>
            RFQs <span class="nav-count">{{ $isStandalone ? ($sidebarCounts['rfqs'] ?? 0) : $quotations->whereIn('status',['open','draft'])->count() }}</span>
        @if($isStandalone)</a>@else</button>@endif

        @if($isStandalone)
            <a class="nav-item {{ $isOrderDetail ? 'active' : '' }}" @if($isOrderDetail)aria-current="page"@endif href="{{ rtrim(route('supplier.home'), '/').'/' }}?view=orders">
        @else
            <button class="nav-item {{ $initialView === 'orders' ? 'active' : '' }}" data-view="orders" type="button">
        @endif
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16v13H4z"/><path d="M8 7V4h8v3M8 11h8"/></svg>
            Orders &amp; Requests <span class="nav-count">{{ $isStandalone ? ($sidebarCounts['orders'] ?? 0) : $orderPage->total() }}</span>
        @if($isStandalone)</a>@else</button>@endif
        <a class="nav-item {{ $isProductPrices ? 'active' : '' }}" href="{{ route('supplier.products.prices') }}" @if($isProductPrices)aria-current="page"@endif>
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="M8 10h8M8 14h5"/></svg>
            Products &amp; Prices
        </a>
    </nav>

    <div class="nav-section-label" style="margin-top:17px">Company</div>
    <nav class="nav" aria-label="Supplier company">
        @if($isStandalone)<a class="nav-item" href="{{ route('supplier.home') }}?view=profile">@else<button class="nav-item {{ $initialView === 'profile' ? 'active' : '' }}" data-view="profile" type="button">@endif
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-5h6v5"/></svg>
            Company Profile
        @if($isStandalone)</a>@else</button>@endif

        @if($isStandalone)<a class="nav-item" href="{{ route('supplier.home') }}?view=notifications">@else<button class="nav-item {{ $initialView === 'notifications' ? 'active' : '' }}" data-view="notifications" type="button">@endif
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
            Notifications <span class="nav-count {{ $unreadCount ? '' : 'hidden' }}">{{ $unreadCount }}</span>
        @if($isStandalone)</a>@else</button>@endif
    </nav>

    <div class="nav-section-label" style="margin-top:17px">Support</div>
    <nav class="nav" aria-label="Supplier support">
        @if($isStandalone)<a class="nav-item" href="{{ route('supplier.home') }}?view=help">@else<button class="nav-item {{ $initialView === 'help' ? 'active' : '' }}" data-view="help" type="button">@endif
            <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M9.8 9a2.4 2.4 0 1 1 3.7 2c-.9.5-1.5 1-1.5 2M12 17h.01"/></svg>
            Help &amp; support
        @if($isStandalone)</a>@else</button>@endif
    </nav>

    <div class="sidebar-bottom">
        <div class="company-mini">
            <small>Signed in as supplier</small>
            <strong>{{ $company->name }}</strong>
            <span>{{ $company->flowtrack_short_code ?: $company->reference }}</span>
            <span><span class="sync-dot" style="width:6px;height:6px;box-shadow:none"></span> Account active</span>
        </div>
        <form method="POST" action="{{ route('supplier.logout') }}" class="supplier-sidebar-logout-form">
            @csrf
            <button class="nav-item supplier-sidebar-logout" type="submit">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a3 3 0 0 1 3 3v12a3 3 0 0 1-3 3h-6"/></svg>
                Log out
            </button>
        </form>
    </div>
</aside>
<div class="supplier-mobile-backdrop" aria-hidden="true"></div>
