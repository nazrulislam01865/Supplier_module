@if(data_get($permissions, 'availability.view', false))
    @php
        $availability = $product['availability'] ?? [];
        $availabilityMode = (string) ($availability['mode'] ?? 'all');
        $canEditAvailability = $edit && (bool) data_get($permissions, 'availability.edit', false);
        $selectedClients = array_map('intval', $availability['client_ids'] ?? []);
    @endphp
    <div class="ft-product-client-scope" data-native-availability>
        <span class="ft-product-field-title">Client availability</span>
        <div class="ft-product-radio-row">
            <label><input type="radio" name="mode" form="supplier-native-availability-form" value="all" @checked($availabilityMode !== 'specific') @disabled(!$canEditAvailability)> All clients</label>
            <label><input type="radio" name="mode" form="supplier-native-availability-form" value="specific" @checked($availabilityMode === 'specific') @disabled(!$canEditAvailability)> Selected clients</label>
        </div>
        <div class="ft-native-availability-client-list" @if($availabilityMode !== 'specific') hidden @endif>
            @if($canEditAvailability)
                <label class="ft-product-field"><span>Select clients</span>
                    <select name="client_ids[]" form="supplier-native-availability-form" multiple size="4" aria-label="Available clients">
                        @foreach(($lookups['clients'] ?? []) as $client)
                            <option value="{{ $client['id'] }}" @selected(in_array((int) $client['id'], $selectedClients, true))>{{ $client['name'] }}</option>
                        @endforeach
                    </select>
                </label>
            @else
                <div class="ft-product-client-badges">
                    @foreach(($availability['labels'] ?? []) as $label)<span>{{ $label }}</span>@endforeach
                </div>
            @endif
        </div>
        @if($canEditAvailability)
            <button type="submit" form="supplier-native-availability-form" class="ft-product-page-btn is-secondary">Save availability</button>
        @elseif(! $edit)
            <small class="ft-product-help">Save the product first to manage client availability.</small>
        @endif
    </div>
@endif
