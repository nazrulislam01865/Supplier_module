<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
@include('portal.partials.flowtrack-favicon')
<title>Edit Supplier Prices · FlowTrack Supplier Portal</title>
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
                <div><h1>Edit Supplier Prices</h1><p>Submit a change for approval. Your proposed prices will not go live until FlowTracker approves them.</p></div>
                <a class="btn btn-outline-teal pricing-back" href="{{ route('supplier.products.prices.show', $product->id) }}"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m12 19-7-7 7-7M5 12h15"/></svg>Back to product</a>
            </header>
            @if(session('success'))<div class="pricing-alert" role="status">{{ session('success') }}</div>@endif
            @if($errors->any())<div class="pricing-alert pricing-alert--error" role="alert">{{ $errors->first() }}</div>@endif
            @php($payload = $product->payload ?? [])
            @php($approvedTiers = is_array($payload['supplier_prices'] ?? null) ? $payload['supplier_prices'] : [])
            @php($pending = $latest?->status === 'pending')
            @php($displayTiers = $pending ? ($latest->proposed_prices ?? []) : old('tiers', $approvedTiers))
            @php($approvedByQuantity = collect($approvedTiers)->keyBy('quantity'))
            <div class="pricing-editor-grid">
                <div class="pricing-editor-left">
                    <section class="pricing-product-summary">
                        @include('portal.partials.pricing-product-image', ['payload' => $payload, 'large' => true])
                        <div class="pricing-product-summary-text"><div class="pricing-product-heading"><h2>{{ $payload['name'] ?? $product->external_reference }}</h2><span class="pricing-active-badge"><span></span>{{ count($approvedTiers) ? 'Active Price' : 'Awaiting First Price' }}</span></div><div class="pricing-product-sku">{{ $payload['code'] ?? $product->external_reference }}</div><div class="pricing-product-timestamp">@if($latest?->status === 'approved')Last approved {{ $latest->updated_at?->diffForHumans() }}@else{{ count($approvedTiers) ? 'Current approved supplier prices' : 'No approved price tiers yet' }}@endif</div></div>
                    </section>
                    @if($latest && $latest->review_note)<p class="pricing-review-note" role="status">Latest request: {{ ucfirst($latest->status) }} — {{ $latest->review_note }}</p>@endif
                    @if($pending)<p class="pricing-pending-note" role="status">Your price request is pending FlowTracker approval. You can review the proposed prices below, but cannot submit another request until it is reviewed. {{ $latest->delivery_status === 'delivered' ? 'FlowTracker has received your request.' : 'Secure delivery will retry automatically.' }}</p>@endif
                    <section class="pricing-editor-card">
                        <header class="pricing-editor-card-header"><h2>Supplier Price Tiers</h2><p>Update your proposed prices for each quantity tier. Enter your new prices below and request approval when ready.</p></header>
                        <form id="supplierPriceEditor" method="post" action="{{ route('supplier.products.prices.submit', $product->id) }}" data-draft-key="supplier-price-draft:{{ (int) $company->id }}:{{ (int) $product->id }}" data-pending="{{ $pending ? '1' : '0' }}" data-baseline-version="{{ $payload['price_version'] ?? '' }}" data-has-old="{{ old('tiers') ? '1' : '0' }}">
                            @csrf
                            <div class="pricing-tier-scroll"><table class="pricing-edit-table">
                                <thead><tr><th scope="col">Quantity</th><th scope="col">Current Approved Price <span>(USD)</span></th><th scope="col">Proposed New Price <span>(USD)</span></th><th scope="col">Status</th><th scope="col"><span class="sr-only">Remove</span></th></tr></thead>
                                <tbody id="priceTierRows">
                                @forelse($displayTiers as $index => $tier)
                                    @php($quantity = (int) ($tier['quantity'] ?? 0))
                                    @php($approved = $approvedByQuantity->get($quantity))
                                    @php($currentPrice = $approved === null ? null : (float) ($approved['price'] ?? 0))
                                    <tr class="pricing-tier-row" data-baseline="{{ $currentPrice === null ? '' : $currentPrice }}">
                                        <td class="pricing-tier-quantity">
                                            @if($approved !== null)
                                                <span>{{ number_format($quantity) }}</span><input class="pricing-quantity-input" type="hidden" name="tiers[{{ $index }}][quantity]" value="{{ $quantity }}" @disabled($pending)>
                                            @else
                                                <input class="pricing-quantity-input" type="number" name="tiers[{{ $index }}][quantity]" min="1" max="100000000" step="1" value="{{ $quantity ?: '' }}" aria-label="Quantity for tier {{ $loop->iteration }}" @disabled($pending) required>
                                            @endif
                                        </td>
                                        <td class="pricing-current-value">{{ $currentPrice === null ? '—' : '$'.number_format($currentPrice, 2) }}</td>
                                        <td><input class="pricing-new-price" type="text" inputmode="decimal" name="tiers[{{ $index }}][price]" value="{{ $tier['price'] ?? '' }}" maxlength="18" pattern="[0-9]+(\.[0-9]{1,6})?" aria-label="Proposed unit price for tier {{ $loop->iteration }}" @disabled($pending) required></td>
                                        
                                        <td><span class="pricing-row-status">Unchanged</span></td>
                                        <td><button class="pricing-remove-row" type="button" aria-label="Remove price tier {{ $loop->iteration }}" @disabled($pending)><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16m-13 0 1 14h8l1-14M9 7V4h6v3M10 11v7m4-7v7"/></svg></button></td>
                                    </tr>
                                @empty
                                    <tr class="pricing-tier-empty"><td colspan="5">No approved price tiers yet. Add a tier to propose prices.</td></tr>
                                @endforelse
                                </tbody>
                            </table></div>
                            @unless($pending)
                                <button type="button" class="btn btn-outline-teal pricing-add-tier" id="addPriceTier"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 7v10M7 12h10"/></svg>Add price tier</button>
                            @endunless
                        </form>
                    </section>
                </div>
            </div>
            <div class="pricing-editor-bottom"><a class="btn pricing-cancel" href="{{ route('supplier.products.prices.show', $product->id) }}">Cancel</a><div class="pricing-editor-cta"><span id="pricingDraftStatus" class="pricing-draft-status" role="status" aria-live="polite"></span>@unless($pending)<button type="button" class="btn btn-outline-teal pricing-save" id="savePriceDraft" form="supplierPriceEditor">Save Draft</button><button type="submit" class="btn btn-primary pricing-submit" form="supplierPriceEditor">Request Price Approval</button>@else<span class="pricing-pill pricing-pill--pending">Pending approval</span>@endunless</div></div>
        </div></main>
    </div>
</section>
<script src="{{ asset('assets/supplier-pricing.js') }}?v={{ filemtime(public_path('assets/supplier-pricing.js')) }}" defer nonce="{{ request()->attributes->get('csp_nonce') }}"></script>
<script src="{{ asset('assets/supplier-catalog-realtime.js') }}?v={{ filemtime(public_path('assets/supplier-catalog-realtime.js')) }}" defer nonce="{{ request()->attributes->get('csp_nonce') }}"></script>
<script src="{{ asset('assets/supplier-mobile.js') }}?v={{ filemtime(public_path('assets/supplier-mobile.js')) }}" defer nonce="{{ request()->attributes->get('csp_nonce') }}"></script>
<script src="{{ asset('assets/supplier-session.js') }}?v={{ filemtime(public_path('assets/supplier-session.js')) }}" data-activity-url="{{ route('supplier.session.activity') }}" data-logout-url="{{ route('supplier.logout') }}" data-login-url="{{ route('supplier.login') }}" defer></script>
</body></html>
