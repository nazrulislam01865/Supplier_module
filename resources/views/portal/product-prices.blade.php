<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('portal.partials.flowtrack-favicon')
<title>Products &amp; Prices · FlowTrack Supplier Portal</title>
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
        <main class="main pricing-main"><div class="content pricing-page">
            <header class="page-header pricing-page-header">
                <div><h1>Products &amp; Prices</h1><p>Products assigned to your company and their approved supplier costs. Customer prices are never shared.</p></div>
                <a class="btn btn-outline-teal pricing-back" href="{{ route('supplier.home') }}"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 19-7-7 7-7M5 12h15"/></svg>Back to dashboard</a>
            </header>
            @if(session('success'))<div class="pricing-alert" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="pricing-alert pricing-alert--error" role="alert">{{ $errors->first() }}</div>@endif
            <div class="pricing-metrics" aria-label="Product pricing overview">
                <div class="pricing-metric"><span class="pricing-metric-icon pricing-metric-icon--products"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 2 9 5-9 5-9-5 9-5ZM3 7v10l9 5 9-5V7M12 12v10"/></svg></span><span class="pricing-metric-copy"><span>Total products</span><strong>{{ number_format($totalProducts) }}</strong></span></div>
                <div class="pricing-metric"><span class="pricing-metric-icon pricing-metric-icon--pending"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/></svg></span><span class="pricing-metric-copy"><span>Products with pending requests</span><strong>{{ number_format((int) ($requestCounts?->pending_count ?? 0)) }}</strong></span></div>
                <div class="pricing-metric"><span class="pricing-metric-icon pricing-metric-icon--approved"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.7"><circle cx="12" cy="12" r="9" fill="currentColor" stroke="none"/><path d="m7.5 12 3 3 6-6" stroke="#fff"/></svg></span><span class="pricing-metric-copy"><span>Approved requests</span><strong>{{ number_format((int) ($requestCounts?->approved_count ?? 0)) }}</strong></span></div>
            </div>
            <form class="pricing-filters" method="get" action="{{ route('supplier.products.prices') }}" role="search">
                <div class="pricing-filter pricing-filter--search"><label for="productSearch">Find a product</label><div class="pricing-searchbox"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.5"/><path d="m16 16 5 5"/></svg><input id="productSearch" name="q" value="{{ $search }}" maxlength="100" placeholder="Search product name or code" autocomplete="off"></div></div>
                <div class="pricing-filter"><label for="productStatus">Product status</label><select id="productStatus" name="product_status"><option value="">All products</option><option value="active" @selected($productStatus === 'active')>Active</option><option value="inactive" @selected($productStatus === 'inactive')>Inactive</option></select></div>
                <div class="pricing-filter"><label for="requestStatus">Request status</label><select id="requestStatus" name="request_status"><option value="">All requests</option><option value="none" @selected($requestStatus === 'none')>No requests</option><option value="pending" @selected($requestStatus === 'pending')>Pending approval</option><option value="approved" @selected($requestStatus === 'approved')>Approved</option><option value="rejected" @selected($requestStatus === 'rejected')>Changes requested</option><option value="stale" @selected($requestStatus === 'stale')>Outdated</option></select></div>
                <div class="pricing-filter-actions"><button class="btn btn-primary pricing-search-button" type="submit">Search</button>@if($search !== '' || $productStatus !== '' || $requestStatus !== '')<a href="{{ route('supplier.products.prices') }}" class="pricing-reset-link">Clear filters</a>@endif</div>
            </form>
            <div class="pricing-table-card"><div class="pricing-table-scroll"><table class="pricing-list-table">
                <thead><tr><th scope="col">Image</th><th scope="col">Product</th><th scope="col">Current supplier price tiers</th><th scope="col">Latest request</th><th scope="col">Last updated</th><th scope="col">Actions</th></tr></thead>
                <tbody>
                @forelse($products as $product)
                    @php($payload = $product->payload ?? [])
                    @php($tiers = is_array($payload['supplier_prices'] ?? null) ? $payload['supplier_prices'] : [])
                    @php($latest = $proposals->get($product->id))
                    <tr>
                        <td class="pricing-image-cell">@include('portal.partials.pricing-product-image', ['payload' => $payload])</td>
                        <td class="pricing-product-cell"><strong>{{ $payload['name'] ?? $product->external_reference }}</strong><span>{{ $payload['code'] ?? $product->external_reference }}</span>@if($product->status !== 'active')<small class="pricing-inactive">Inactive product</small>@endif</td>
                        <td class="pricing-tiers-cell">
                            @if(count($tiers))
                                <strong>{{ count($tiers) }} {{ count($tiers) === 1 ? 'tier' : 'tiers' }}</strong>
                                <span>From {{ number_format((int) ($tiers[0]['quantity'] ?? 0)) }} {{ (int) ($tiers[0]['quantity'] ?? 0) === 1 ? 'unit' : 'units' }} · ${{ number_format((float) ($tiers[0]['price'] ?? 0), 2) }}</span>
                                <button type="button" class="pricing-tier-popup-trigger" data-price-table-trigger
                                    data-price-table-id="pricing-tier-template-{{ $product->id }}"
                                    data-product-name="{{ $payload['name'] ?? $product->external_reference }}"
                                    data-product-code="{{ $payload['code'] ?? $product->external_reference }}"
                                    data-tier-count="{{ count($tiers) }}"
                                    aria-haspopup="dialog" aria-controls="supplierPriceTableDialog">
                                    View price table
                                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M7 17 17 7M8 7h9v9"/></svg>
                                </button>
                                <template id="pricing-tier-template-{{ $product->id }}">
                                    @include('portal.partials.pricing-supplier-tier-table', ['tiers' => $tiers])
                                </template>
                            @else
                                <span>No supplier-specific price assigned</span>
                            @endif
                        </td>
                        <td class="pricing-request-cell">
                            @if(!$latest)<span class="pricing-pill pricing-pill--none">No requests</span><small>—</small>
                            @else
                                <span class="pricing-pill pricing-pill--{{ $latest->status }}">@if($latest->status === 'pending')<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4"><circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/></svg>Pending approval @elseif($latest->status === 'approved')<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><path d="m4 12 5 5L20 6"/></svg>Approved @elseif($latest->status === 'rejected')<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6"><circle cx="12" cy="12" r="9"/><path d="M12 7v6m0 4h.01"/></svg>Changes requested @else{{ ucfirst($latest->status) }}@endif</span>
                                <small>{{ $latest->status === 'approved' ? 'Approved' : 'Requested' }} {{ ($latest->status === 'approved' ? $latest->updated_at : $latest->created_at)?->format('M j, Y') }}</small>
                            @endif
                        </td>
                        <td class="pricing-updated">{{ $product->updated_at?->format('M j, Y') ?? '—' }}</td>
                        <td class="pricing-list-actions"><a class="btn btn-outline-teal pricing-action" href="{{ route('supplier.products.prices.show', $product->id) }}">View</a></td>
                    </tr>
                @empty<tr><td colspan="6" class="pricing-empty">{{ ($search !== '' || $productStatus !== '' || $requestStatus !== '') ? 'No assigned products match your filters.' : 'No products are currently assigned to your company.' }}</td></tr>@endforelse
                </tbody>
            </table></div>
            <div class="pricing-table-footer"><span>Showing {{ $products->firstItem() ?? 0 }} to {{ $products->lastItem() ?? 0 }} of {{ number_format($products->total()) }} products</span><div class="pricing-pagination">{{ $products->withQueryString()->onEachSide(2)->links('portal.partials.pricing-pagination') }}</div></div>
            </div>
        </div></main>
    </div>
    <dialog class="pricing-price-dialog" id="supplierPriceTableDialog" aria-labelledby="supplierPriceDialogTitle" aria-describedby="supplierPriceDialogDescription">
        <div class="pricing-price-dialog-panel">
            <header class="pricing-price-dialog-head">
                <div>
                    <span class="pricing-price-dialog-eyebrow">FLOWTRACKER APPROVED PRICING</span>
                    <h2 id="supplierPriceDialogTitle">Supplier price table</h2>
                    <p id="supplierPriceDialogDescription">Supplier-specific unit prices in USD</p>
                </div>
                <button class="pricing-price-dialog-dismiss" type="button" data-price-dialog-close aria-label="Close price table">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6 6 18M6 6l12 12"/></svg>
                </button>
            </header>
            <div class="pricing-price-dialog-body">
                <div class="pricing-price-dialog-product">
                    <div class="pricing-price-dialog-product-info">
                        <span>PRODUCT</span>
                        <strong id="supplierPriceDialogProduct"></strong>
                        <small id="supplierPriceDialogCode"></small>
                    </div>
                    <span class="pricing-price-dialog-count" id="supplierPriceDialogCount"></span>
                </div>
                <div class="pricing-price-dialog-table" id="supplierPriceDialogTable"></div>
                <p class="pricing-price-dialog-note">
                    <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.6 2.8 8.5 7 10 4.2-1.5 7-5.4 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg>
                    These are your supplier-specific approved prices synced from FlowTracker.
                </p>
            </div>
            <footer class="pricing-price-dialog-foot">
                <button class="btn btn-outline-teal pricing-price-dialog-done" type="button" data-price-dialog-close>Close</button>
            </footer>
        </div>
    </dialog>
</section>
<script src="{{ asset('assets/supplier-pricing.js') }}?v={{ filemtime(public_path('assets/supplier-pricing.js')) }}" defer nonce="{{ request()->attributes->get('csp_nonce') }}"></script>
<script src="{{ asset('assets/supplier-catalog-realtime.js') }}?v={{ filemtime(public_path('assets/supplier-catalog-realtime.js')) }}" defer nonce="{{ request()->attributes->get('csp_nonce') }}"></script>
<script src="{{ asset('assets/supplier-mobile.js') }}?v={{ filemtime(public_path('assets/supplier-mobile.js')) }}" defer nonce="{{ request()->attributes->get('csp_nonce') }}"></script>
<script src="{{ asset('assets/supplier-session.js') }}?v={{ filemtime(public_path('assets/supplier-session.js')) }}" data-activity-url="{{ route('supplier.session.activity') }}" data-logout-url="{{ route('supplier.logout') }}" data-login-url="{{ route('supplier.login') }}" defer></script>
</body></html>
