<div class="supplier-orders-pagination">
    <span>
        @if($orderPage->total())
            Showing {{ $orderPage->firstItem() }}–{{ $orderPage->lastItem() }} of {{ number_format($orderPage->total()) }} orders
        @else
            Showing 0 of 0 orders
        @endif
    </span>
    @php
        $current = $orderPage->currentPage();
        $last = $orderPage->lastPage();
        $pageNumbers = collect([1, max(1, $current - 1), $current, min($last, $current + 1), $last])->unique()->sort()->values();
    @endphp
    <nav class="supplier-orders-page-controls" aria-label="Order pages">
        @if($orderPage->onFirstPage())
            <span class="supplier-orders-page-button is-disabled" aria-disabled="true" aria-label="Previous page">←</span>
        @else
            <a class="supplier-orders-page-button" href="{{ $orderPage->previousPageUrl() }}" aria-label="Previous page">←</a>
        @endif
        @foreach($pageNumbers as $number)
            <a class="supplier-orders-page-button {{ $current === $number ? 'is-current' : '' }}" href="{{ $orderPage->url($number) }}" @if($current === $number) aria-current="page" @endif>{{ $number }}</a>
        @endforeach
        @if($orderPage->hasMorePages())
            <a class="supplier-orders-page-button" href="{{ $orderPage->nextPageUrl() }}" aria-label="Next page">→</a>
        @else
            <span class="supplier-orders-page-button is-disabled" aria-disabled="true" aria-label="Next page">→</span>
        @endif
    </nav>
</div>
