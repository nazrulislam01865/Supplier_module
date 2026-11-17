@php
    $recordId = (int) ($details['id'] ?? 0);
    $product = $details;
    $perm = fn (string $section, string $action = 'view') => (bool) data_get($permissions, $section.'.'.$action, false);
    $price = $product['supplier_pricing'] ?? [];
    $clientPrice = $product['client_pricing'] ?? [];
    $shipment = $product['shipment'] ?? [];
    $availability = $product['availability'] ?? [];
    $assets = collect($product['documents'] ?? []);
    $currencies = collect($lookups['currencies'] ?? []);
@endphp
@if($perm('supplier_pricing') || $perm('client_pricing') || $perm('availability'))
<section class="ft-product-page-section" id="product-pricing">
    <div class="ft-product-page-section-heading">
        <span class="ft-product-page-section-number">3</span>
        <div><h2>Product pricing</h2><p>Maintain supplier costs and client selling prices separately. Client prices automatically apply in orders and inquiries.</p></div>
    </div>
    <div class="ft-product-page-section-body">
        @include('portal.product-native.sections.supplier_pricing')
        @include('portal.product-native.sections.client_pricing')
        </div>
</section>
@endif
@if($perm('options'))
<section class="ft-product-page-section ft-product-options-section">
    <div class="ft-product-page-section-heading">
        <span class="ft-product-page-section-number">4</span>
        <div><h2>Product options</h2><p>Add optional choices such as color, size or material. An option can also add an extra unit charge.</p></div>
    </div>
    <div class="ft-product-page-section-body">@include('portal.product-native.sections.options')</div>
</section>
@endif
@if($perm('shipment'))
<section class="ft-product-page-section ft-product-shipping-section">
    <div class="ft-product-page-section-heading">
        <span class="ft-product-page-section-number">5</span>
        <div><h2>Shipping urgencies</h2><p>Manage shipment urgency and additional charges.</p></div>
    </div>
    <div class="ft-product-page-section-body">@include('portal.product-native.sections.shipment')</div>
</section>
@endif
@if($perm('documents'))
<section class="ft-product-page-section">
    <div class="ft-product-page-section-heading">
        <span class="ft-product-page-section-number">6</span>
        <div><h2>Supporting documents</h2><p>Add product files or replace them while editing.</p></div>
    </div>
    <div class="ft-product-page-section-body">@include('portal.product-native.sections.documents')</div>
</section>
@endif
