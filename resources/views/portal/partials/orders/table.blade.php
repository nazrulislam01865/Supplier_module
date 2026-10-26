<div class="supplier-orders-table-viewport">
    <table class="supplier-orders-table">
        <thead><tr>
            <th>Order</th><th>Product</th><th>Current Stage</th><th>Status</th><th>Supplier Delivery Date</th><th>Progress</th>
        </tr></thead>
        <tbody>
        @forelse($orders as $order)
            @php
                $detailsUrl = route('supplier.orders.show', $order['id']);
                $rowAccent = $order['hold'] ? '#FF6A00' : $order['stage_color'];
            @endphp
            <tr class="supplier-order-row {{ $order['hold'] ? 'is-on-hold' : '' }}" style="--supplier-order-accent:{{ $rowAccent }}" data-supplier-order-href="{{ $detailsUrl }}" tabindex="0" aria-label="Open order {{ $order['reference'] }}">
                <td data-label="Order">
                    <a class="supplier-order-ref" href="{{ $detailsUrl }}">{{ $order['reference'] }}</a>
                    <span class="supplier-order-subline">{{ $order['created'] }}</span>
                    @if($order['hold'])<span class="supplier-order-hold-label">Ⅱ On Hold</span>@endif
                </td>
                <td data-label="Product">
                    <div class="supplier-order-product-cell">
                        <span class="supplier-order-product-avatar" aria-hidden="true">P</span>
                        <div class="supplier-order-product-copy"><strong title="{{ $order['products'] }}">{{ $order['products'] ?: 'Product not specified' }}</strong></div>
                    </div>
                </td>
                <td data-label="Current Stage"><span class="supplier-order-stage" style="--supplier-order-stage:{{ $order['stage_color'] }}"><span class="supplier-order-stage-dot"></span>{{ $order['stage'] }}</span></td>
                <td data-label="Status"><span class="supplier-order-status {{ $order['hold'] ? 'is-held' : '' }}">{{ $order['status'] }}</span>@if($order['hold'])<small class="supplier-order-flag">On hold</small>@endif</td>
                <td data-label="Supplier Delivery Date"><span class="supplier-order-delivery-date">{{ $order['supplier_delivery'] ?: 'Not set' }}</span></td>
                <td data-label="Progress"><div class="supplier-order-progress"><span class="supplier-order-progress-track"><span style="width:{{ $order['progress'] ?? 0 }}%"></span></span><strong>{{ $order['progress'] === null ? '—' : $order['progress'].'%' }}</strong></div></td>
            </tr>
        @empty
            <tr><td class="supplier-orders-empty" colspan="6"><strong>No matching orders</strong><span>Change the search or filters to see your assigned orders.</span></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
