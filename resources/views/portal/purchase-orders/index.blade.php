@extends('portal.purchase-orders.layout')
@section('page-title', 'Purchase Orders')
@section('po-list-style', '1')
@section('content')
<div class="ft-po-page ft-po-supplier-list">
    <header class="ft-po-page-header">
        <div>
            <div class="breadcrumbs">Order / Purchase Orders</div>
            <h1>Purchase Orders</h1>
            <p>Review and edit supplier purchase orders generated through the optional Artwork task.</p>
        </div>
        <a class="ft-po-back" href="{{ route('supplier.home', ['view' => 'orders']) }}">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m15 18-6-6 6-6"/></svg>
            Back to Orders
        </a>
    </header>

    <section class="ft-po-list" aria-label="Orders available for purchase orders">
        <div class="ft-po-list-heading">
            <div><h2>Order purchase orders</h2><p>Find an order to review its supplier Purchase Orders. Generation is available only after the optional Artwork task becomes ready.</p></div>
            <span class="ft-po-list-hint">15 orders per page</span>
        </div>
        <form method="GET" action="{{ route('supplier.purchase-orders.index') }}" class="ft-po-filter-toolbar" role="search">
            <input type="hidden" name="po_filter" value="{{ $poFilter }}">
            <label class="ft-po-search">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.3"/><path d="m16 16 4.4 4.4"/></svg>
                <input name="search" type="search" value="{{ $search }}" placeholder="Search order, reference or product" aria-label="Search orders" maxlength="120" autocomplete="off">
            </label>
            <div class="ft-po-filter-controls" role="group" aria-label="Filter by purchase order status">
                <a href="{{ route('supplier.purchase-orders.index', ['search' => $search, 'po_filter' => 'all']) }}" @class(['ft-po-filter-chip', 'is-active' => $poFilter === 'all']) aria-current="{{ $poFilter === 'all' ? 'page' : 'false' }}">All orders</a>
                <a href="{{ route('supplier.purchase-orders.index', ['search' => $search, 'po_filter' => 'pending']) }}" @class(['ft-po-filter-chip', 'is-active' => $poFilter === 'pending']) aria-current="{{ $poFilter === 'pending' ? 'page' : 'false' }}">Not generated</a>
                <a href="{{ route('supplier.purchase-orders.index', ['search' => $search, 'po_filter' => 'generated']) }}" @class(['ft-po-filter-chip', 'is-active' => $poFilter === 'generated']) aria-current="{{ $poFilter === 'generated' ? 'page' : 'false' }}">Generated</a>
                @if($search !== '' || $poFilter !== 'all')
                    <a class="ft-po-clear" href="{{ route('supplier.purchase-orders.index') }}">Clear filters</a>
                @endif
            </div>
        </form>
        <div class="ft-po-table-summary">
            <span><strong>{{ $orders->count() }}</strong> {{ $orders->count() === 1 ? 'order' : 'orders' }} on this page</span>
            <span>{{ $poFilter === 'pending' ? 'Not generated' : ($poFilter === 'generated' ? 'Generated supplier POs' : 'All PO statuses') }}</span>
        </div>
        <div class="ft-po-table-scroll">
            <table class="ft-po-table">
                <thead><tr><th>Order</th><th>Product</th><th>Current stage</th><th>Order status</th><th>PO status</th><th>Action</th></tr></thead>
                <tbody>
                @forelse($orders as $order)
                    @php
                        $data = $order->payload ?? [];
                        $pos = $data['purchase_orders'] ?? [];
                        $hasPo = count($pos) > 0;
                        $available = ! empty($data['po_creation_available']) && ! $hasPo;
                        $product = (string) ($data['items'][0]['product_name'] ?? $data['title'] ?? 'No product');
                        $productCode = (string) ($data['items'][0]['product_code'] ?? $data['items'][0]['sku'] ?? 'Product');
                        $itemCount = count($data['items'] ?? []);
                        $stage = (string) ($data['stage_name'] ?? $data['listing']['stage_name'] ?? 'Not started');
                        $stageColor = match (strtolower(trim($stage))) {
                            'new order' => '#316bc5', 'artwork' => '#7b61c9',
                            'production' => '#d17b1f', 'qc', 'quality control' => '#138d7a',
                            'shipment' => '#1873a8', default => '#64748b',
                        };
                        $status = (string) ($data['status'] ?? $data['order_status'] ?? $order->status ?? '—');
                        $statusClass = match (strtolower(trim($status))) {
                            'new' => 'is-new', 'skipped' => 'is-skipped',
                            'completed', 'complete' => 'is-completed',
                            'in progress', 'in_progress' => 'is-progress', default => 'is-neutral',
                        };
                        $date = substr((string) ($data['created_at'] ?? ''), 0, 10);
                        $displayDate = $date !== '' ? date('M j, Y', strtotime($date)) : '—';
                    @endphp
                    <tr @class(['ft-po-order-row', 'has-po' => $hasPo])>
                        <td data-label="Order" class="ft-po-order-identity">
                            <a class="ft-po-order-id" href="{{ route('supplier.orders.show', $order->id) }}">{{ $order->external_reference }}</a>
                            <small>{{ $data['title'] ?? 'No reference' }} <span aria-hidden="true">·</span> {{ $displayDate }}</small>
                        </td>
                        <td data-label="Product" class="ft-po-client-product">
                            <div class="ft-po-client-product-row">
                                <span class="ft-po-product-avatar" aria-hidden="true">{{ mb_strtoupper(mb_substr($productCode, 0, 1)) }}</span>
                                <span><strong title="{{ $productCode }}">{{ $productCode }}</strong><small title="{{ $product }}">{{ $product }}@if($itemCount > 1) <span class="ft-po-more-count">+{{ $itemCount - 1 }} more</span>@endif</small></span>
                            </div>
                        </td>
                        <td data-label="Current stage"><span class="ft-po-stage-chip" style="--po-stage-color:{{ $stageColor }}">{{ $stage }}</span></td>
                        <td data-label="Order status"><span class="ft-po-order-status {{ $statusClass }}">{{ $status }}</span></td>
                        <td data-label="PO status"><span @class(['ft-po-status', 'is-ready' => $hasPo])><span class="ft-po-status-dot" aria-hidden="true"></span>{{ $hasPo ? 'Generated' : 'Not generated' }}</span></td>
                        <td data-label="Action">
                            @if($hasPo || $available)
                                <a class="ft-po-view-action" href="{{ route('supplier.purchase-orders.show', $order->id) }}">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M7 3h8l4 4v14H7z"/><path d="M15 3v5h5M10 13h6M10 17h6"/></svg>
                                    {{ $hasPo ? 'View POs' : 'Open PO' }}
                                </a>
                            @else
                                <span class="ft-po-row-dash" aria-label="PO not available">—</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="ft-po-empty"><strong>No orders found</strong><span>Try another search or choose a different PO status.</span></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="ft-po-pagination">{{ $orders->links() }}</div>
    </section>
</div>
@endsection
