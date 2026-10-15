<div class="supplier-prototype-recent-item is-{{ $update['status'] }}">
    <div class="supplier-prototype-recent-dot"><span></span></div>
    <div class="supplier-prototype-recent-copy">
        <strong>{{ $update['title'] }}</strong>
        <span>{{ $update['date'] }}</span>
        @if(filled($update['description']))<p>{{ $update['description'] }}</p>@endif
    </div>
</div>
