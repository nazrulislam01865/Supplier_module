@php
    // The portal is mounted at /supplier/ in production. Submit to that canonical
    // URL so the web server never redirects /supplier?filters to /supplier/.
    $ordersBaseUrl = rtrim(route('supplier.home'), '/').'/';
@endphp
<form id="supplierOrderFilters" class="supplier-orders-filter-toolbar" method="GET" action="{{ $ordersBaseUrl }}" role="search" aria-label="Filter supplier orders">
    <input type="hidden" name="view" value="orders">
    @if($orderFilters['quick'] !== '')<input type="hidden" name="quick" value="{{ $orderFilters['quick'] }}">@endif
    <label class="supplier-orders-search">
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4 4"/></svg>
        <input type="search" name="search" value="{{ $orderFilters['search'] }}" placeholder="Search order, reference or product" autocomplete="off" aria-label="Search order, reference or product">
    </label>
    <div class="supplier-orders-filter-controls">
        <a href="{{ $ordersBaseUrl }}?view=orders" class="supplier-orders-filter-chip {{ !$hasOrderFilters ? 'is-active' : '' }}" aria-current="{{ !$hasOrderFilters ? 'true' : 'false' }}">All</a>
        <label class="supplier-orders-filter-chip supplier-orders-hold {{ $orderFilters['hold'] ? 'is-active' : '' }}">
            <input type="checkbox" name="hold" value="1" @checked($orderFilters['hold'])>
            <span>Hold on</span>
        </label>
        <select name="stage" aria-label="Workflow stage" class="supplier-orders-filter-select supplier-orders-stage">
            <option value="">All stages</option>
            @foreach($orderStages as $id => $name)
                <option value="{{ $id }}" @selected($orderFilters['stage'] === $id)>{{ $name }}</option>
            @endforeach
        </select>
        <label class="supplier-orders-date"><span>From</span><input type="date" name="from" value="{{ $orderFilters['from'] }}" aria-label="Created date from"></label>
        <label class="supplier-orders-date"><span>To</span><input type="date" name="to" value="{{ $orderFilters['to'] }}" min="{{ $orderFilters['from'] }}" aria-label="Created date to"></label>
        @if($hasOrderFilters)
            <a class="supplier-orders-clear" href="{{ $ordersBaseUrl }}?view=orders"><span aria-hidden="true">×</span> Clear</a>
        @else
            <span class="supplier-orders-clear is-disabled" aria-disabled="true"><span aria-hidden="true">×</span> Clear</span>
        @endif
    </div>
    <button class="supplier-orders-noscript-submit" type="submit">Apply filters</button>
</form>
