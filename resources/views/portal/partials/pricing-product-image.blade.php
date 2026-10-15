@php
    $version = (string) ($payload['image_version'] ?? '');
    $hasImage = preg_match('/^[a-f0-9]{64}$/', $version) && isset($product) && $product?->id;
@endphp
<div class="pricing-thumb {{ ($large ?? false) ? 'pricing-thumb--large' : '' }}" aria-label="Product image">
    <svg viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="10" y="13" width="44" height="38" rx="3"/><circle cx="24" cy="27" r="5"/><path d="m14 47 12-13 9 9 6-7 9 11"/></svg>
    @if($hasImage)<img src="{{ route('supplier.products.prices.image', $product->id) }}?v={{ $version }}" alt="{{ $payload['name'] ?? 'Product' }}" loading="lazy" decoding="async">@endif
</div>
