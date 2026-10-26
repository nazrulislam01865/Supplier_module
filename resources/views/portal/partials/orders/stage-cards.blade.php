{{-- Supplier-visible stages and supplier-side metrics share the same reusable card. --}}
@if(count($orderStageCards))
    <section class="supplier-order-stages" aria-label="Supplier orders by workflow stage">
        <div class="supplier-order-stage-grid">
            @foreach($orderStageCards as $stage)
                @php
                    $isQuick = $stage['kind'] === 'quick';
                    $selected = $isQuick
                        ? $orderFilters['quick'] === $stage['quick']
                        : $orderFilters['quick'] === '' && $orderFilters['stage'] === $stage['sequence'];
                    $query = array_filter([
                        'view' => 'orders',
                        'search' => $orderFilters['search'],
                        'hold' => $orderFilters['hold'] ? '1' : '',
                        'stage' => $isQuick || $selected ? '' : $stage['sequence'],
                        'quick' => $isQuick && !$selected ? $stage['quick'] : '',
                        'from' => $orderFilters['from'],
                        'to' => $orderFilters['to'],
                    ], static fn ($value) => $value !== '');
                    $label = $isQuick ? $stage['name'] : $stage['name'].' stage';
                @endphp
                <a href="{{ route('supplier.home', $query) }}"
                   class="supplier-order-stage-card {{ $selected ? 'is-selected' : '' }}"
                   style="--supplier-stage:{{ $stage['color'] }}"
                   aria-label="{{ $selected ? 'Clear' : 'Filter by' }} {{ $label }}, {{ number_format($stage['count']) }} {{ $stage['count'] === 1 ? 'order' : 'orders' }}"
                   @if($selected) aria-current="true" @endif>
                    @if($selected)<span class="supplier-order-stage-check" aria-hidden="true">✓</span>@endif
                    <span class="supplier-order-stage-kicker">{{ $isQuick ? $stage['kicker'] : 'Stage '.$stage['sequence'] }}</span>
                    <strong class="supplier-order-stage-name">{{ $stage['name'] }}</strong>
                    <span class="supplier-order-stage-bottom">
                        <span>{{ $isQuick ? 'Current filters' : 'Current orders' }}</span>
                        <b>{{ number_format($stage['count']) }}</b>
                    </span>
                </a>
            @endforeach
        </div>
    </section>
@endif
