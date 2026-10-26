@php
    $hasOrderFilters = $orderFilters['search'] !== '' || $orderFilters['hold'] || $orderFilters['stage'] || $orderFilters['quick'] !== ''
        || $orderFilters['from'] !== '' || $orderFilters['to'] !== '';
@endphp
<div class="supplier-orders-list" id="supplierOrdersList">
    @include('portal.partials.orders.filters')
    <div class="supplier-orders-list-summary" role="status">
        <span>{{ number_format($orderPage->total()) }} orders</span>
        <span>{{ $orderFilters['quick'] === 'today' ? 'Created Today filter' : ($orderFilters['quick'] === 'completed' ? 'Supplier completed filter' : ($orderFilters['stage'] ? ($orderStages[$orderFilters['stage']] ?? 'Stage').' filter' : 'Showing all workflow stages')) }}</span>
    </div>
    @include('portal.partials.orders.table')
    @include('portal.partials.orders.pagination')
</div>
