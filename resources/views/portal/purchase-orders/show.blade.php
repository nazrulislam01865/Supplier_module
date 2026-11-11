@extends('portal.purchase-orders.layout')
@section('page-title', 'Order '.$order->external_reference.' · Purchase Orders')
@section('content')
<div class="po-content">
    <div class="po-breadcrumb"><a href="{{ route('supplier.purchase-orders.index') }}">Purchase Orders</a><span>/</span><strong>{{ $order->external_reference }}</strong></div>
    <header class="po-heading">
        <div><h1>Purchase Orders</h1><p>Order {{ $order->external_reference }} · {{ $payload['title'] ?? '' }}</p></div>
        <a class="po-link" href="{{ route('supplier.orders.show', $order->id) }}">View order details</a>
    </header>
    @if(session('success'))<div class="po-message po-message-success" role="status">{{ session('success') }}</div>@endif
    @if($errors->any())<div class="po-message po-message-error" role="alert">{{ $errors->first() }}</div>@endif
    @if(count($pos) > 1)
    <nav class="po-selection" aria-label="Select Purchase Order">
        @foreach($pos as $po)
            <a href="{{ route('supplier.purchase-orders.show', ['id' => $order->id, 'po' => $po['id']]) }}" class="po-selection-link {{ (int) ($selected['id'] ?? 0) === (int) $po['id'] ? 'selected' : '' }}">{{ $po['number'] }}</a>
        @endforeach
    </nav>
    @endif
    <div class="po-columns">
        <div class="po-primary">
            <section class="po-card">
                <div class="po-card-heading"><h2>{{ $selected ? ($editing ? 'Edit Purchase Order' : 'Purchase Order Details') : 'Create Purchase Order' }}</h2>
                    <div class="po-heading-actions">@if($selected)<span class="po-pill po-saved">Revision {{ $selected['revision'] }}</span>@if($editing)<a class="po-button po-button-outline" href="{{ route('supplier.purchase-orders.show', ['id' => $order->id, 'po' => $selected['id']]) }}">Cancel edit</a>@else<a class="po-button" href="{{ route('supplier.purchase-orders.show', ['id' => $order->id, 'po' => $selected['id'], 'edit' => 1]) }}">Edit PO</a>@endif @elseif($canCreate)<span class="po-pill po-ready">Ready</span>@endif</div>
                </div>
                @if($selected && ! $editing)
                    <div class="po-form">
                        <div class="po-field-row"><div class="po-field"><label>Linked order</label><div class="po-readonly">{{ $order->external_reference }}</div></div><div class="po-field"><label>PO delivery date</label><div class="po-readonly">{{ $selected['delivery_date'] ?? 'Not set' }}</div></div></div>
                        <div class="po-table-scroller"><table class="po-table"><thead><tr><th>Product</th><th>Model / SKU</th><th>Qty</th><th>Supplier unit price</th><th>Total</th></tr></thead><tbody>
                        @foreach($rows as $line)<tr><td>{{ $line['name'] }}</td><td>{{ $line['sku'] ?? '—' }}</td><td>{{ number_format((int) $line['quantity']) }}</td><td>{{ $line['supplier_unit_cost'] === null ? '—' : number_format((float) $line['supplier_unit_cost'], 2) }}</td><td>{{ $line['supplier_unit_cost'] === null ? '—' : number_format((int) $line['quantity'] * (float) $line['supplier_unit_cost'], 2) }}</td></tr>@endforeach
                        </tbody></table></div>
                    </div>
                @elseif($selected && $editing)
                    <form method="POST" action="{{ route('supplier.purchase-orders.save', $order->id) }}" class="po-form" id="supplierPoForm">
                        @csrf
                        <input type="hidden" name="po_id" value="{{ $selected['id'] ?? '' }}">
                        <input type="hidden" name="revision" value="{{ $selected['revision'] ?? '' }}">
                        <div class="po-field-row">
                            <div class="po-field"><label>Linked order</label><div class="po-readonly">{{ $order->external_reference }}</div></div>
                            <div class="po-field"><label for="po-delivery">PO delivery date</label><input type="date" id="po-delivery" name="delivery_date" value="{{ old('delivery_date', $selected['delivery_date'] ?? ($payload['delivery_date'] ?? '')) }}" @if(!empty($payload['delivery_date'])) max="{{ $payload['delivery_date'] }}" @endif></div>
                        </div>
                        <p class="po-note">Changing this date or the product values updates the Purchase Order only. It does not change the original Order.</p>
                        <div class="po-table-scroller"><table class="po-table po-edit-table">
                            <thead><tr><th>Product</th><th>Model / SKU</th><th>Qty</th><th>Supplier unit price</th></tr></thead>
                            <tbody>
                            @foreach($rows as $i => $line)
                                <tr>
                                    <td><input type="hidden" name="items[{{ $i }}][item_id]" value="{{ $line['item_id'] }}"><input type="text" name="items[{{ $i }}][name]" aria-label="Product name" required maxlength="255" value="{{ old('items.'.$i.'.name', $line['name']) }}"></td>
                                    <td><input type="text" name="items[{{ $i }}][sku]" aria-label="Model or SKU" maxlength="100" value="{{ old('items.'.$i.'.sku', $line['sku'] ?? '') }}"></td>
                                    <td><input type="number" name="items[{{ $i }}][quantity]" aria-label="Quantity" required min="1" max="999999999" step="1" value="{{ old('items.'.$i.'.quantity', $line['quantity']) }}"></td>
                                    <td><input type="number" name="items[{{ $i }}][supplier_unit_cost]" aria-label="Supplier unit price" min="0" max="999999999.99" step="0.01" value="{{ old('items.'.$i.'.supplier_unit_cost', $line['supplier_unit_cost'] ?? '') }}"></td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table></div>
                        <div class="po-actions"><button class="po-button" type="submit">{{ $selected ? 'Save PO Changes' : 'Create PO' }}</button></div>
                    </form>
                @else
                    <div class="po-card-body">
                        @if($canCreate)
                            <p>Artwork approval and the sample step are complete. Choose Single PO or Split POs using the original Purchase Order generator.</p>
                            <a class="po-button" href="{{ route('supplier.purchase-orders.show', ['id' => $order->id, 'create' => 1]) }}">Create PO</a>
                        @else
                            <p>The optional Purchase Order step is not ready, was skipped, or the Artwork stage has not been completed. No PO is created automatically.</p>
                        @endif
                    </div>
                @endif
            </section>
        </div>
        <aside class="po-secondary">
            <section class="po-card">
                <div class="po-card-heading"><h2>Documents</h2></div>
                <div class="po-card-body">
                    @if($selected && ! empty($selected['document']))
                        <a class="po-document" href="{{ route('supplier.purchase-orders.download', ['id' => $order->id, 'po' => $selected['id'], 'document' => $selected['document']['id']]) }}"><span>Purchase Order PDF</span><small>{{ $selected['document']['name'] }}</small></a>
                    @else
                        <p class="po-muted">The Purchase Order PDF appears after the PO is created.</p>
                    @endif
                    @if($tracking)
                        <a class="po-document" href="{{ route('supplier.orders.artwork', ['id' => $order->id, 'document' => $tracking['id']]) }}"><span>Artwork Tracking PDF</span><small>{{ $tracking['name'] }}</small></a>
                    @else
                        <p class="po-muted">The Artwork Tracking PDF will appear here when available in the confirmed artwork documents.</p>
                    @endif
                </div>
            </section>
            <section class="po-card"><div class="po-card-heading"><h2>Synchronization</h2></div>
                <div class="po-card-body"><p>Changes are submitted directly to FlowTracker. The latest accepted PO revision is then synchronized to this Portal.</p>
                    @if($selected)<p class="po-muted">{{ $selected['number'] }} · Revision {{ $selected['revision'] }}</p>@endif
                </div>
            </section>
        </aside>
    </div>
</div>
@if($creating)
    @include('portal.purchase-orders.partials.create-modal')
@endif
@endsection
