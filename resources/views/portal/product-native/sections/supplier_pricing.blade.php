@if($perm('supplier_pricing'))
<div class="ft-product-supplier-price-list">
  <div class="ft-product-price-section-header"><strong>Supplier-based price tables (1)</strong></div>
  <small>Each tagged supplier has its own quantity-based cost table. Approved supplier requests appear here automatically. Supplier costs do not affect client selling prices.</small>
  <div class="ft-product-client-price-entry ft-native-supplier-edit-card">
    <strong>{{ $company->name }}</strong>
  @if($perm('supplier_pricing','edit'))<form action="{{ route('supplier.products.module.action') }}" method="POST">@csrf
    <input type="hidden" name="operation" value="save_pricing"><input type="hidden" name="group" value="product"><input type="hidden" name="id" value="{{ $recordId }}"><input type="hidden" name="section" value="supplier_pricing"><input type="hidden" name="base_version" value="{{ $price['version'] ?? '' }}"><input type="hidden" name="charges_version" value="{{ $price['charges_version'] ?? '' }}">
    <label class="ft-product-field" style="margin:12px 0;max-width:280px"><span>Supplier price currency</span><select name="currency">
      @foreach($currencies->isNotEmpty() ? $currencies : collect([['code'=>'USD','name'=>'USD'],['code'=>'CNY','name'=>'CNY']]) as $currency)
        <option value="{{ $currency['code'] }}" @selected(($price['currency'] ?? 'USD') === $currency['code'])>{{ $currency['code'] }} — {{ $currency['name'] }}</option>
      @endforeach
    </select></label>
    <div class="ft-product-matrix-toolbar"><small>Enter the quantity in the first row and supplier unit price below it. Surcharges and shipment charges are optional per quantity.</small></div>
    @include('portal.product-native.matrix', ['section'=>'supplier_pricing','rows'=>$price['rows'] ?? [],'charges'=>$price['charges'] ?? [],'editable'=>true,'title'=>'Supplier pricing'])
    <div class="ft-native-form-actions"><button type="submit" class="ft-native-btn is-primary">Save supplier pricing</button></div>
  </form>@else
@if(!empty($price['rows']))
@include('portal.product-native.matrix', ['section'=>'supplier_pricing','rows'=>$price['rows'] ?? [],'charges'=>$price['charges'] ?? [],'editable'=>false,'title'=>'Supplier pricing'])
@else<p class="ft-price-empty-state">No quantity-based prices configured for this currency.</p>@endif
@endif
  @if(!empty($price['additional_tables']))<div class="ft-price-additional-heading">Additional currency tables</div>@endif
  @foreach(($price['additional_tables'] ?? []) as $extraTable)
    @include('portal.product-native.supplier-currency-table', [
        'extraTable' => $extraTable, 'editable' => $perm('supplier_pricing','edit'),
    ])
  @endforeach
  @if($perm('supplier_pricing','edit'))
    <details class="ft-supplier-currency-add ft-price-currency" style="margin-top:2px">
      <summary class="ft-price-currency-heading"><span class="ft-price-currency-identity"><strong>+ Add currency table</strong></span><span class="ft-price-currency-meta">Choose a currency and quantities<span class="ft-price-chevron" aria-hidden="true"></span></span></summary>
      <div class="ft-price-currency-body">
      <form method="POST" action="{{ route('supplier.products.module.action') }}" style="margin-top:14px">
        @csrf
        <input type="hidden" name="operation" value="save_pricing">
        <input type="hidden" name="group" value="product">
        <input type="hidden" name="id" value="{{ $recordId }}">
        <input type="hidden" name="section" value="supplier_currency_table">
        <input type="hidden" name="base_version" value="{{ $price['new_table_version'] ?? '' }}">
        <label class="ft-product-field" style="display:block;max-width:300px;margin-bottom:14px">
          <span>Supplier price currency</span>
          <select name="currency" required>
            <option value="">Select currency</option>
            @foreach($currencies as $currency)
              @if($currency['code'] !== ($price['currency'] ?? 'USD') &&
                  !collect($price['additional_tables'] ?? [])->contains('currency', $currency['code']))
                <option value="{{ $currency['code'] }}">{{ $currency['code'] }} — {{ $currency['name'] }}</option>
              @endif
            @endforeach
          </select>
        </label>
        @include('portal.product-native.matrix', [
            'section'=>'supplier_pricing', 'rows'=>[], 'charges'=>[], 'editable'=>true,
            'title'=>'New supplier currency table',
        ])
        <div class="ft-native-form-actions"><button class="ft-product-page-btn is-primary" type="submit">Save currency table</button></div>
      </form>
      </div>
    </details>
  @endif
  </div>
</div>
@endif
