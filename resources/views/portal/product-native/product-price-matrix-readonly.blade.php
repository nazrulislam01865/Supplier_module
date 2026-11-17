@php
    $priceRows = collect($rows ?? [])->values();
    $additionalCharges = collect($charges ?? [])->keyBy('quantity');
    $supplierTable = (bool) ($supplierMatrix ?? false);
    $labels = $supplierTable
        ? ['price' => 'Supplier price', 'remote_surcharge' => 'Remote surcharge', 'standard_shipment' => 'Standard shipment', 'urgent_shipment' => 'Urgent shipment']
        : ['price' => 'Client price'];
@endphp
<table class="ft-product-price-preview ft-product-detail-matrix">
    <thead><tr><th scope="col">Quantity</th>
        @forelse($priceRows as $priceRow)
            <th scope="col">{{ number_format((int) $priceRow['quantity']) }}</th>
        @empty
            <th scope="col" class="ft-product-price-empty">&nbsp;</th>
        @endforelse
    </tr></thead>
    <tbody>
        @foreach($labels as $field => $fieldLabel)
            <tr><th scope="row">{{ $fieldLabel }}</th>
                @forelse($priceRows as $priceRow)
                    @php
                        $value = $field === 'price'
                            ? ($priceRow['price'] ?? null)
                            : data_get($additionalCharges->get((int) $priceRow['quantity']), $field);
                    @endphp
                    <td>{{ $value === null || $value === '' ? '' : ((float) $value === 0.0 ? '0' : rtrim(rtrim(number_format((float) $value, 6, '.', ''), '0'), '.')) }}</td>
                @empty
                    <td class="ft-product-price-empty">&nbsp;</td>
                @endforelse
            </tr>
        @endforeach
    </tbody>
</table>
