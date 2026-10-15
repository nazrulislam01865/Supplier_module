{{-- Both screens use only supplier_prices from the signed supplier-scoped catalogue. --}}
<div class="pricing-tier-scroll">
    <table class="pricing-details-table @if($compact ?? false) pricing-inline-tier-table @endif">
        <thead><tr><th scope="col">Quantity</th><th scope="col">Supplier unit price (USD)</th></tr></thead>
        <tbody>
        @forelse($tiers as $tier)
            <tr><td>{{ number_format((int) $tier['quantity']) }}</td><td>${{ number_format((float) $tier['price'], 2) }}</td></tr>
        @empty
            <tr><td colspan="2" class="pricing-tier-empty">No supplier-specific price has been assigned in FlowTracker.</td></tr>
        @endforelse
        </tbody>
    </table>
</div>
