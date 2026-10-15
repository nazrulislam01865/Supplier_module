@php
    $summaryClass = 'is-'.$stage['status'];
@endphp
<div class="supplier-prototype-stage-summary {{ $summaryClass }}">
    <div class="supplier-prototype-stage-summary-icon">
        @if($stage['status'] === 'completed')
            @include('portal.order.partials.svg.check')
        @else
            <span class="supplier-prototype-stage-summary-ring"></span>
        @endif
    </div>
    <div class="supplier-prototype-stage-summary-copy">
        <div class="supplier-prototype-stage-summary-top">
            <strong>{{ $stage['name'] }}</strong>
            <span class="supplier-prototype-status-pill is-{{ $stage['status'] }}">{{ $stage['status_label'] }}</span>
        </div>
        <span>{{ $stage['helper'] }}</span>
    </div>
</div>
