@if($perm('client_pricing'))
<div class="ft-product-client-price-list"><div class="ft-product-price-section-header"><strong>Client price tables ({{ count($clientPrice['client_rows'] ?? []) ?: 1 }})</strong></div><small>One quantity-based selling-price table applies to all clients. Supplier costs remain separate.</small>
  <div class="ft-native-price-label">All clients · {{ $clientPrice['currency'] ?? 'USD' }}</div>
  @if($perm('client_pricing','edit'))<form method="post" action="{{ route('supplier.products.module.action') }}">@csrf
    <input type="hidden" name="operation" value="save_pricing"><input type="hidden" name="group" value="product"><input type="hidden" name="id" value="{{ $recordId }}"><input type="hidden" name="section" value="client_pricing">
    <label class="ft-product-field" style="display:block;max-width:280px;margin:12px 0"><span>Client price currency</span><select name="currency">@foreach($currencies->isNotEmpty() ? $currencies : collect([['code'=>'USD','name'=>'USD'],['code'=>'CNY','name'=>'CNY']]) as $currency)<option value="{{ $currency['code'] }}" @selected(($clientPrice['currency'] ?? 'USD') === $currency['code'])>{{ $currency['code'] }} — {{ $currency['name'] }}</option>@endforeach</select></label>
    <div class="ft-product-matrix-toolbar"><small>Enter quantities in the first row and corresponding client unit prices below.</small></div>
    @include('portal.product-native.matrix', ['section'=>'client_pricing','rows'=>$clientPrice['rows'] ?? [],'editable'=>true,'title'=>'All clients price table'])
    <div class="ft-native-form-actions"><button class="ft-native-btn is-primary">Save client pricing</button></div>
  </form>@else
@include('portal.product-native.matrix', ['section'=>'client_pricing','rows'=>$clientPrice['rows'] ?? [],'editable'=>false,'title'=>'All clients price table'])@endif
  @if(($clientPrice['client_rows'] ?? []) !== [])
   <div class="ft-native-subsection"><h3>Selected-client price overrides</h3>
    @foreach($clientPrice['client_rows'] as $over)
      <div class="ft-native-price-label">{{ collect($lookups['clients'] ?? [])->firstWhere('id', $over['client_id'])['name'] ?? ('Client #'.$over['client_id']) }} · {{ $over['currency'] ?? 'USD' }}</div>
      @if($perm('client_pricing','edit'))
       <form method="post" action="{{ route('supplier.products.module.action') }}">@csrf
        <input type="hidden" name="operation" value="save_pricing"><input type="hidden" name="group" value="product"><input type="hidden" name="id" value="{{ $recordId }}"><input type="hidden" name="section" value="client_override">
        <input type="hidden" name="client_id" value="{{ $over['client_id'] }}"><input type="hidden" name="base_version" value="{{ $over['version'] }}">
        @include('portal.product-native.matrix', ['section'=>'client_pricing','rows'=>$over['rows'] ?? [],'editable'=>true,'title'=>'Selected client price override'])
        <div class="ft-native-form-actions">
          <label class="ft-native-currency"><span>Currency</span><select name="currency">
            @foreach($currencies as $currency)<option value="{{ $currency['code'] }}" @selected(($over['currency'] ?? 'USD') === $currency['code'])>{{ $currency['code'] }} — {{ $currency['name'] }}</option>@endforeach
          </select></label>
          <button class="ft-native-btn is-primary" type="submit">Save client override</button>
          <button class="ft-native-btn is-danger" type="submit" name="remove" value="1" onclick="return confirm('Remove this selected-client pricing table?')">Remove override</button>
        </div>
       </form>
      @else
       @include('portal.product-native.matrix', ['section'=>'client_pricing','rows'=>$over['rows'] ?? [],'editable'=>false,'title'=>'Client price override'])
      @endif
    @endforeach
   </div>
  @endif
  @if($perm('client_pricing','edit'))
    <div class="ft-native-subsection"><h3>Add selected-client price table</h3>
     <form method="POST" action="{{ route('supplier.products.module.action') }}">@csrf
      <input type="hidden" name="operation" value="save_pricing"><input type="hidden" name="group" value="product"><input type="hidden" name="id" value="{{ $recordId }}"><input type="hidden" name="section" value="client_override">
      <div class="ft-native-grid ft-native-grid-2"><label><span>Client</span><select name="client_id" required><option value="">Select client</option>
      @foreach(($lookups['clients'] ?? []) as $client)<option value="{{ $client['id'] }}">{{ $client['name'] }}</option>@endforeach
      </select></label>
      <label><span>Currency</span><select name="currency">@foreach($currencies as $currency)<option value="{{ $currency['code'] }}" @selected($currency['code'] === ($clientPrice['currency'] ?? 'USD'))>{{ $currency['code'] }} — {{ $currency['name'] }}</option>@endforeach</select></label></div>
      @include('portal.product-native.matrix', ['section'=>'client_pricing','rows'=>[],'editable'=>true,'title'=>'New client price override'])
      <div class="ft-native-form-actions"><button class="ft-native-btn is-primary" type="submit">Save selected-client prices</button></div>
     </form>
    </div>
  @endif
</div>
@endif
