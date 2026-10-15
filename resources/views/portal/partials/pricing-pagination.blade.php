@if($paginator->hasPages())
<nav role="navigation" aria-label="Product pages" class="pricing-pager-links">
    @if($paginator->onFirstPage())<span class="pricing-page-arrow is-disabled" aria-hidden="true">‹</span>@else<a class="pricing-page-arrow" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page">‹</a>@endif
    @foreach($elements as $element)
        @if(is_string($element))<span class="pricing-page-gap">{{ $element }}</span>@else
            @foreach($element as $page => $url)
                @if($page == $paginator->currentPage())<span class="pricing-page-number is-current" aria-current="page">{{ $page }}</span>
                @else<a class="pricing-page-number" href="{{ $url }}" aria-label="Page {{ $page }}">{{ $page }}</a>@endif
            @endforeach
        @endif
    @endforeach
    @if($paginator->hasMorePages())<a class="pricing-page-arrow" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page">›</a>@else<span class="pricing-page-arrow is-disabled" aria-hidden="true">›</span>@endif
</nav>
@endif
