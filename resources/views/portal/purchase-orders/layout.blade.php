<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('portal.partials.flowtrack-favicon')
    <title>@yield('page-title', 'Purchase Orders') · FlowTrack Supplier Portal</title>
    <link rel="stylesheet" href="{{ asset('assets/supplier.css') }}?v={{ filemtime(public_path('assets/supplier.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/supplier-mobile.css') }}?v={{ filemtime(public_path('assets/supplier-mobile.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/supplier-purchase-orders.css') }}?v={{ filemtime(public_path('assets/supplier-purchase-orders.css')) }}">
    @hasSection('po-list-style')
        <link rel="stylesheet" href="{{ asset('assets/supplier-po-list.css') }}?v={{ filemtime(public_path('assets/supplier-po-list.css')) }}">
    @endif
</head>
<body class="supplier-prototype-body" @if(isset($order)) id="supplierOrderView"
    data-order-realtime-id="{{ $order->id }}"
    data-order-realtime-revision="{{ (string) ($order->payload['revision'] ?? '0') }}"
    data-order-realtime-action-pending="{{ session('success') ? '1' : '0' }}"
    data-order-realtime-supplier="{{ (int) $company->flowtrack_supplier_id }}"
    data-order-realtime-source="{{ (int) ($order->payload['source_id'] ?? 0) }}"
    data-order-realtime-key="{{ (string) config('supplier_realtime.key') }}"
    data-order-realtime-host="{{ (string) config('supplier_realtime.host') }}"
    data-order-realtime-port="{{ (int) config('supplier_realtime.port') }}"
    data-order-realtime-scheme="{{ (string) config('supplier_realtime.scheme') }}"
    data-order-realtime-auth="{{ route('supplier.orders.realtime.auth', $order->id) }}"
    data-order-realtime-revision-url="{{ route('supplier.orders.realtime.revision', $order->id) }}" @endif>
<section id="portalView">
    <header class="topbar">
        <div class="topbar-left">
            <button class="btn btn-ghost btn-sm mobile-menu" id="mobileMenuBtn" aria-label="Open menu" type="button">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <a class="brand" href="{{ route('supplier.home') }}">@include('portal.partials.flowtrack-brand')</a>
        </div>
        <div class="topbar-right">
            <span class="sync-state"><span class="sync-dot"></span>Secure supplier session</span>
            <a class="notification-btn" href="{{ route('supplier.home') }}?view=notifications" aria-label="Notifications">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                @if($hasUnreadNotifications)<span class="notification-dot"></span>@endif
            </a>
            <div class="account">
                <div class="avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                <div class="account-meta"><strong>{{ $user->name }}</strong><small>{{ $company->name }}</small></div>
                <a class="icon-button" href="{{ route('supplier.home') }}?view=profile" aria-label="Company profile">
                    <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 9 6 6 6-6"/></svg>
                </a>
            </div>
        </div>
    </header>
    <div class="shell">
        @include('portal.partials.sidebar', ['isPurchaseOrders' => true])
        <main class="main po-main">
            @yield('content')
        </main>
    </div>
</section>
<script src="{{ asset('assets/supplier-po-form.js') }}?v={{ filemtime(public_path('assets/supplier-po-form.js')) }}" defer nonce="{{ request()->attributes->get('csp_nonce') }}"></script>
<script src="{{ asset('assets/supplier-mobile.js') }}?v={{ filemtime(public_path('assets/supplier-mobile.js')) }}" defer nonce="{{ request()->attributes->get('csp_nonce') }}"></script>
@if(isset($order))
<script src="{{ asset('assets/supplier-order-realtime.js') }}?v={{ filemtime(public_path('assets/supplier-order-realtime.js')) }}" defer nonce="{{ request()->attributes->get('csp_nonce') }}"></script>
@endif
<script src="{{ asset('assets/supplier-session.js') }}?v={{ filemtime(public_path('assets/supplier-session.js')) }}" data-activity-url="{{ route('supplier.session.activity') }}" data-logout-url="{{ route('supplier.logout') }}" data-login-url="{{ route('supplier.login') }}" defer></script>
</body>
</html>
