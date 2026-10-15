<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('portal.partials.flowtrack-favicon')
<title>Product Details · FlowTrack Supplier Portal</title>
<link rel="stylesheet" href="{{ asset('assets/supplier.css') }}?v={{ filemtime(public_path('assets/supplier.css')) }}">
<link rel="stylesheet" href="{{ asset('assets/supplier-pricing.css') }}?v={{ filemtime(public_path('assets/supplier-pricing.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/supplier-mobile.css') }}?v={{ filemtime(public_path('assets/supplier-mobile.css')) }}">
</head>
<body>
<section id="portalView" data-catalog-revision="{{ (string) $company->catalog_revision }}"
    data-catalog-supplier="{{ (int) $company->flowtrack_supplier_id }}"
    data-catalog-key="{{ (string) config('supplier_realtime.key') }}"
    data-catalog-host="{{ (string) config('supplier_realtime.host') }}"
    data-catalog-port="{{ (int) config('supplier_realtime.port') }}"
    data-catalog-scheme="{{ (string) config('supplier_realtime.scheme') }}"
    data-catalog-auth="{{ route('supplier.products.prices.realtime.auth') }}"
    data-catalog-revision-url="{{ route('supplier.products.prices.realtime.revision') }}">
    <header class="topbar">
        <div class="topbar-left">
            <button class="btn btn-ghost btn-sm mobile-menu" id="mobileMenuBtn" aria-label="Open menu" type="button"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
            <a class="brand" href="{{ route('supplier.home') }}">@include('portal.partials.flowtrack-brand')</a>
        </div>
        <div class="topbar-right">
            <span class="sync-state"><span class="sync-dot"></span>Secure supplier session</span>
            <a class="notification-btn" href="{{ route('supplier.home') }}?view=notifications" aria-label="Notifications"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg></a>
            <div class="account"><div class="avatar">{{ strtoupper(mb_substr(request()->user()->name, 0, 1)) }}</div><div class="account-meta"><strong>{{ request()->user()->name }}</strong><small>{{ $company->name }}</small></div><a class="icon-button" href="{{ route('supplier.home') }}?view=profile" aria-label="Company profile"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 9 6 6 6-6"/></svg></a></div>
        </div>
    </header>
    <div class="shell">
        @include('portal.partials.sidebar', ['isProductPrices' => true])
        <main class="main pricing-main"><div class="content pricing-page pricing-editor-page">
            <header class="page-header pricing-page-header">
                <div><h1>Product Details</h1><p>View your assigned product and its approved supplier prices.</p></div>
                <a class="btn btn-outline-teal pricing-back" href="{{ route('supplier.products.prices') }}"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 19-7-7 7-7M5 12h15"/></svg>Back to products</a>
            </header>
            @if(session('success'))<div class="pricing-alert" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="pricing-alert pricing-alert--error" role="alert">{{ $errors->first() }}</div>@endif
            @php($payload = $product->payload ?? [])
            @php($tiers = is_array($payload['supplier_prices'] ?? null) ? $payload['supplier_prices'] : [])
            <section class="pricing-product-summary pricing-product-detail-summary">
                @include('portal.partials.pricing-product-image', ['payload' => $payload, 'large' => true])
                <div class="pricing-product-summary-text">
                    <div class="pricing-product-heading"><h2>{{ $payload['name'] ?? $product->external_reference }}</h2><span class="pricing-active-badge {{ $product->status !== 'active' ? 'pricing-active-badge--inactive' : '' }}"><span></span>{{ $product->status === 'active' ? 'Active product' : 'Inactive product' }}</span></div>
                    <div class="pricing-product-sku">{{ $payload['code'] ?? $product->external_reference }}</div>
                    <div class="pricing-product-timestamp">Last updated {{ $product->updated_at?->format('M j, Y') ?? '—' }}</div>
                </div>
                @if($product->status === 'active' && $latest?->status !== 'pending')
                    <a class="btn btn-primary pricing-detail-edit" href="{{ route('supplier.products.prices.edit', $product->id) }}">Edit Prices</a>
                @endif
            </section>
            @if($latest?->status === 'pending')
                <div class="pricing-pending-note" role="status">A price request is pending approval. Current approved prices remain unchanged, and editing is unavailable until it is reviewed.</div>
            @elseif($latest && $latest->review_note)
                <div class="pricing-review-note" role="status">Latest request: {{ ucfirst($latest->status) }} — {{ $latest->review_note }}</div>
            @endif
            <div class="pricing-detail-grid">
                <section class="pricing-editor-card pricing-detail-prices">
                    <header class="pricing-editor-card-header"><h2>Current Supplier Price Tiers</h2><p>Supplier-specific prices approved in FlowTracker (USD).</p></header>
                    @include('portal.partials.pricing-supplier-tier-table', ['tiers' => $tiers])
                </section>
                <section class="pricing-editor-card pricing-detail-request">
                    <header class="pricing-editor-card-header"><h2>Latest Price Request</h2></header>
                    @if($latest)
                        <div class="pricing-detail-meta"><span>Request status</span><span class="pricing-pill pricing-pill--{{ $latest->status }}">{{ match ($latest->status) { 'pending' => 'Pending approval', 'approved' => 'Approved', 'rejected' => 'Changes requested', 'stale' => 'Outdated', default => ucfirst($latest->status) } }}</span></div>
                        <div class="pricing-detail-meta"><span>Submitted</span><span>{{ $latest->created_at?->format('M j, Y') ?? '—' }}</span></div>
                        @if($latest->status === 'approved')<div class="pricing-detail-meta"><span>Approved</span><span>{{ $latest->updated_at?->format('M j, Y') ?? '—' }}</span></div>@endif
                    @else
                        <p class="pricing-detail-no-requests">No price change requests for this product.</p>
                    @endif
                </section>
            </div>
        </div></main>
    </div>
</section>
<script src="{{ asset('assets/supplier-pricing.js') }}?v={{ filemtime(public_path('assets/supplier-pricing.js')) }}" defer nonce="{{ request()->attributes->get('csp_nonce') }}"></script>
<script src="{{ asset('assets/supplier-catalog-realtime.js') }}?v={{ filemtime(public_path('assets/supplier-catalog-realtime.js')) }}" defer nonce="{{ request()->attributes->get('csp_nonce') }}"></script>
<script src="{{ asset('assets/supplier-mobile.js') }}?v={{ filemtime(public_path('assets/supplier-mobile.js')) }}" defer nonce="{{ request()->attributes->get('csp_nonce') }}"></script>
<script src="{{ asset('assets/supplier-session.js') }}?v={{ filemtime(public_path('assets/supplier-session.js')) }}" data-activity-url="{{ route('supplier.session.activity') }}" data-logout-url="{{ route('supplier.logout') }}" data-login-url="{{ route('supplier.login') }}" defer></script>
</body></html>
