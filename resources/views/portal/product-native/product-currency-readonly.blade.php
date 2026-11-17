@php
    $currencyRows = collect($rows ?? [])->values();
    $primaryTable = (bool) ($primary ?? false);
@endphp
<details class="ft-price-currency" open>
    <summary class="ft-price-currency-heading">
        <span class="ft-price-currency-identity"><strong>{{ $currency }}</strong><span class="ft-price-badge {{ $primaryTable ? 'is-primary' : 'is-muted' }}">{{ $primaryTable ? 'Primary table' : 'Additional currency' }}</span></span>
        <span class="ft-price-currency-meta">{{ $currencyRows->isNotEmpty() ? $currencyRows->count().' quantity '.($currencyRows->count() === 1 ? 'tier' : 'tiers') : 'No prices set' }}<span class="ft-price-chevron" aria-hidden="true"></span></span>
    </summary>
    <div class="ft-price-currency-body">
        @if($currencyRows->isNotEmpty())
            <div class="ft-product-price-preview-wrap ft-product-detail-price-wrap ft-price-matrix-scroll" role="region" aria-label="{{ $company->name }} {{ $currency }} prices" tabindex="0">
                @include('portal.product-native.product-price-matrix-readonly', ['rows' => $currencyRows->all(), 'charges' => $charges ?? [], 'supplierMatrix' => true])
            </div>
        @else
            <p class="ft-price-empty-state">No quantity-based prices configured for this currency.</p>
        @endif
    </div>
</details>
