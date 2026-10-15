@if($paginator->hasPages())
<nav role="navigation" aria-label="Notification pages" class="supplier-notification-pages">
    @if($paginator->onFirstPage())
        <span class="supplier-notification-page-button is-disabled" aria-disabled="true">Previous</span>
    @else
        <a class="supplier-notification-page-button" href="{{ $paginator->previousPageUrl() }}" rel="prev">Previous</a>
    @endif
    @foreach($elements as $element)
        @if(is_string($element))
            <span class="supplier-notification-page-ellipsis">{{ $element }}</span>
        @else
            @foreach($element as $page => $url)
                @if($page == $paginator->currentPage())
                    <span class="supplier-notification-page-button is-current" aria-current="page">{{ $page }}</span>
                @else
                    <a class="supplier-notification-page-button" href="{{ $url }}" aria-label="Page {{ $page }}">{{ $page }}</a>
                @endif
            @endforeach
        @endif
    @endforeach
    @if($paginator->hasMorePages())
        <a class="supplier-notification-page-button" href="{{ $paginator->nextPageUrl() }}" rel="next">Next</a>
    @else
        <span class="supplier-notification-page-button is-disabled" aria-disabled="true">Next</span>
    @endif
</nav>
@endif
