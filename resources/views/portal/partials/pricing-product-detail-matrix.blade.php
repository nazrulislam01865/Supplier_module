{{-- Identical quantity-column structure to FlowTracker Product Details. No calculated prices. --}}
@php
    $prices = collect(is_array($tiers) ? $tiers : [])->filter(fn($row) => isset($row['quantity']) && isset($row['price']))->sortBy('quantity')->values();
    $chargeRows = collect(is_array($charges ?? null) ? $charges : [])->keyBy('quantity');
    $currency = preg_match('/^[A-Z]{3}$/', (string) ($currency ?? 'USD')) ? $currency : 'USD';
@endphp
<div class="pricing-ft-matrix-wrap">
    <strong class="pricing-ft-matrix-name">{{ $supplierName ?? 'Supplier' }} · {{ $currency }}</strong>
    <div class="pricing-ft-matrix-scroll">
        <table class="pricing-ft-matrix">
            <thead><tr><th scope="col">Quantity</th>
                @forelse($prices as $row)<th scope="col">{{ number_format((int) $row['quantity']) }}</th>
                @empty<th scope="col">&nbsp;</th>@endforelse
            </tr></thead>
            <tbody>
                @foreach(['price' => 'Supplier price', 'remote_surcharge' => 'Remote surcharge', 'standard_shipment' => 'Standard shipment', 'urgent_shipment' => 'Urgent shipment'] as $field => $label)
                    <tr><th scope="row">{{ $label }}</th>
                        @forelse($prices as $row)
                            @php $value = $field === 'price' ? $row['price'] : data_get($chargeRows->get((int) $row['quantity']), $field); @endphp
                            <td>{{ $value === null || $value === '' ? '' : ((float)$value === 0.0 ? '0' : rtrim(rtrim(number_format((float)$value, 6, '.', ''), '0'), '.')) }}</td>
                        @empty<td>&nbsp;</td>@endforelse
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</div>
