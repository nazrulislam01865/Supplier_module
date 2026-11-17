@php
  $matrixRows = collect($rows ?? [])->values();
  $chargeMap = collect($charges ?? [])->keyBy('quantity');
  $isSupplierMatrix = ($section ?? '') === 'supplier_pricing';
  $matrixColumns = max(1, $matrixRows->count());
  $fieldPrefix = $fieldPrefix ?? 'tiers';
@endphp
<div class="ft-native-matrix-wrap ft-product-price-preview-wrap ft-product-matrix-scroll" data-native-matrix>
    <div class="ft-native-matrix-scroll" role="region" aria-label="{{ $title ?? 'Pricing' }}" tabindex="0">
        <table class="ft-native-price-table ft-product-price-preview ft-product-price-matrix">
            <thead><tr><th scope="col" class="ft-native-rowhead">Quantity</th>
                @for($i=0; $i < $matrixColumns; $i++)
                    <th class="ft-native-tier-cell">@if($editable ?? false)<input type="number" min="1" max="100000000" step="1" name="{{ $fieldPrefix }}[{{ $i }}][quantity]" value="{{ old($fieldPrefix.'.'.$i.'.quantity', $matrixRows[$i]['quantity'] ?? '') }}" placeholder="Quantity" aria-label="Quantity {{ $i+1 }}">@else{{ isset($matrixRows[$i]['quantity']) ? number_format((int) $matrixRows[$i]['quantity']) : '' }}@endif</th>
                @endfor
            </tr></thead>
            <tbody>
                @foreach(array_merge(['price'], $isSupplierMatrix ? ['remote_surcharge', 'standard_shipment', 'urgent_shipment'] : []) as $field)
                    <tr><th scope="row" class="ft-native-rowhead">{{ match($field) { 'price' => $isSupplierMatrix ? 'Supplier price' : ($fieldPrefix === 'remote_surcharge' ? 'Remote surcharge per unit' : 'Client price'), 'remote_surcharge' => 'Remote surcharge', 'standard_shipment' => 'Standard shipment', default => 'Urgent shipment' } }}</th>
                        @for($i=0; $i < $matrixColumns; $i++)
                            @php $quantity = $matrixRows[$i]['quantity'] ?? 0; $value = $field === 'price' ? ($matrixRows[$i]['price'] ?? null) : data_get($chargeMap->get($quantity), $field); @endphp
                            <td class="ft-native-tier-cell">@if($editable ?? false)<input type="number" step="0.000001" min="0" name="{{ $fieldPrefix }}[{{ $i }}][{{ $field }}]" value="{{ old($fieldPrefix.'.'.$i.'.'.$field, $value) }}" placeholder="—" aria-label="{{ $field }} {{ $i+1 }}">@else{{ $value !== null && $value !== '' ? number_format((float) $value, 2, '.', '') : '' }}@endif</td>
                        @endfor
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if($editable ?? false)<div class="ft-native-matrix-controls ft-product-matrix-toolbar"><button type="button" class="ft-native-add-tier">+ Add quantity</button><button type="button" class="ft-native-remove-tier">Remove last quantity</button><small>Quantity-based unit prices. Empty cells are not saved.</small></div>@endif
</div>
