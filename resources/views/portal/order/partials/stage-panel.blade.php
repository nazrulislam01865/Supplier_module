@php
    $panelClass = 'is-'.$stage['status'];
@endphp
<section class="supplier-prototype-stage-panel {{ $panelClass }}">
    <div class="supplier-prototype-stage-rail">
        <div class="supplier-prototype-stage-rail-dot is-{{ $stage['status'] }}">
            @if($stage['status'] === 'completed')
                @include('portal.order.partials.svg.check')
            @endif
        </div>
    </div>
    <div class="supplier-prototype-stage-panel-card">
        <header class="supplier-prototype-stage-panel-head">
            <div class="supplier-prototype-stage-panel-title-wrap">
                <div class="supplier-prototype-stage-panel-icon is-{{ $stage['status'] }}">@include('portal.order.partials.svg.folder')</div>
                <div class="supplier-prototype-stage-panel-title">
                    <strong>{{ $stage['name'] }}</strong>
                    <span class="supplier-prototype-status-pill is-{{ $stage['status'] }}">{{ $stage['status_label'] }}</span>
                </div>
            </div>
            <div class="supplier-prototype-stage-panel-meta">
                @if($stage['headline_date'] !== '—')
                    <span>{{ $stage['headline_date'] }}</span>
                @endif
                <button type="button" class="supplier-prototype-collapse-indicator {{ $stage['status'] === 'upcoming' ? 'is-collapsed' : '' }}" aria-label="Toggle {{ $stage['name'] }} tasks" aria-controls="supplier-stage-{{ $stage['key'] }}" aria-expanded="{{ $stage['status'] === 'upcoming' ? 'false' : 'true' }}" data-stage-toggle>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 14 6-6 6 6"/></svg>
                </button>
            </div>
        </header>

        <div class="supplier-prototype-stage-details" id="supplier-stage-{{ $stage['key'] }}" @if($stage['status'] === 'upcoming') hidden @endif>
        @if(!empty($stage['tasks']))
            <div class="supplier-prototype-stage-task-list">
                @foreach($stage['tasks'] as $task)
                    <div class="supplier-prototype-stage-task-row">
                        <div class="supplier-prototype-stage-task-name">
                            @include('portal.order.partials.svg.file-doc')
                            <span>{{ $task['index'] }}. {{ $task['name'] }}</span>
                        </div>
                        <div class="supplier-prototype-stage-task-status is-{{ $task['status'] }}">
                            <span class="supplier-prototype-stage-task-status-icon"></span>
                            <strong>{{ $task['status_label'] }}</strong>
                        </div>
                        <div class="supplier-prototype-stage-task-date">{{ $task['date_label'] }}</div>
                    </div>
                @endforeach
            </div>
        @endif

        @if($stage['key'] === 'artwork' && $stage['document'])
            <div class="supplier-prototype-stage-artwork-box">
                <div class="supplier-prototype-stage-artwork-head">Approved artwork</div>
                <div class="supplier-prototype-stage-artwork-row">
                    <div class="supplier-prototype-artwork-media">
                        <div class="supplier-prototype-artwork-thumb supplier-prototype-artwork-thumb--large supplier-prototype-file-placeholder" aria-hidden="true">
                            @include('portal.order.partials.svg.file-doc')
                        </div>
                        <div class="supplier-prototype-artwork-copy">
                            <strong>{{ $stage['document']['name'] }}</strong>
                            <span>{{ number_format(($stage['document']['size'] ?? 0) / 1048576, 1) }} MB &nbsp; • &nbsp; {{ strtoupper(pathinfo($stage['document']['name'], PATHINFO_EXTENSION) ?: 'FILE') }}</span>
                        </div>
                    </div>
                    <a class="supplier-prototype-view-btn" href="{{ route('supplier.orders.artwork', [$order->id, $stage['document']['id']]) }}">Download</a>
                </div>
            </div>
        @endif
        </div>
    </div>
</section>
