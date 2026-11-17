<details class="ft-price-currency ft-supplier-currency-entry" open>
    <summary class="ft-price-currency-heading">
        <span class="ft-price-currency-identity"><strong>{{ $extraTable['currency'] }}</strong><span class="ft-price-badge is-muted">Additional currency</span></span>
        <span class="ft-price-currency-meta">{{ count($extraTable['rows'] ?? []) ? count($extraTable['rows']).' quantity tiers' : 'No prices set' }}<span class="ft-price-chevron" aria-hidden="true"></span></span>
    </summary>
    <div class="ft-price-currency-body">
    @if($editable ?? false)
        <form method="POST" action="{{ route('supplier.products.module.action') }}" class="ft-supplier-currency-form">
            @csrf
            <input type="hidden" name="operation" value="save_pricing">
            <input type="hidden" name="group" value="product">
            <input type="hidden" name="id" value="{{ $recordId }}">
            <input type="hidden" name="section" value="supplier_currency_table">
            <input type="hidden" name="currency" value="{{ $extraTable['currency'] }}">
            <input type="hidden" name="base_version" value="{{ $extraTable['version'] }}">
            @include('portal.product-native.matrix', [
                'section'=>'supplier_pricing', 'rows'=>$extraTable['rows'] ?? [],
                'charges'=>$extraTable['charges'] ?? [], 'editable'=>true,
                'title'=>'Additional supplier currency '.$extraTable['currency'],
            ])
            <div class="ft-native-form-actions">
                <button type="submit" class="ft-product-page-btn is-primary">Save {{ $extraTable['currency'] }} table</button>
                <button type="submit" name="remove" value="1" class="ft-product-page-btn is-secondary"
                    formnovalidate onclick="return confirm('Remove this additional currency price table?')">Remove table</button>
            </div>
        </form>
    @else
        @if(!empty($extraTable['rows']))
        @include('portal.product-native.product-price-matrix-readonly', [
            'rows'=>$extraTable['rows'] ?? [], 'charges'=>$extraTable['charges'] ?? [], 'supplierMatrix'=>true,
        ])
        @else<p class="ft-price-empty-state">No quantity-based prices configured for this currency.</p>@endif
    @endif
    </div>
</details>
