@if($perm('availability'))
<section class="ft-native-card ft-native-section"><div class="ft-native-section-heading"><div><h2>Classification &amp; availability</h2></div></div>
  <p class="ft-native-subtitle">{{ ($product['availability']['mode'] ?? 'all') === 'specific' ? 'Selected clients' : 'All clients' }}</p>
  @if(($product['availability']['labels'] ?? []) !== [])<div class="ft-native-chips">@foreach($product['availability']['labels'] as $clientName)<span>{{ $clientName }}</span>@endforeach</div>@endif
  @if($perm('availability','edit'))<form method="POST" action="{{ route('supplier.products.module.action') }}" class="ft-native-inline-form">@csrf
    <input type="hidden" name="operation" value="save_pricing"><input type="hidden" name="group" value="product"><input type="hidden" name="id" value="{{ $recordId }}"><input type="hidden" name="section" value="availability">
    <div class="ft-native-grid ft-native-grid-2"><label><span>Client availability</span><select name="mode"><option value="all" @selected(($product['availability']['mode'] ?? 'all') !== 'specific')>All clients</option><option value="specific" @selected(($product['availability']['mode'] ?? '') === 'specific')>Selected clients</option></select></label>
    <label><span>Selected clients (hold Command/Ctrl to choose multiple)</span><select name="client_ids[]" multiple size="4">@foreach(($lookups['clients'] ?? []) as $client)<option value="{{ $client['id'] }}" @selected(in_array((int) $client['id'],array_map('intval',$product['availability']['client_ids'] ?? []),true))>{{ $client['name'] }}</option>@endforeach</select></label></div>
    <div class="ft-native-form-actions"><button class="ft-native-btn is-primary" type="submit">Save availability</button></div>
  </form>@endif
</section>
@endif
