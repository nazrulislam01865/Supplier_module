@php
    $product = $details;
    $recordId = (int) ($product['id'] ?? 0);
    $perm = fn (string $section, string $action = 'view') => (bool) data_get($permissions, $section.'.'.$action, false);
    $price = $product['supplier_pricing'] ?? [];
    $clientPrice = $product['client_pricing'] ?? [];
    $shipment = $product['shipment'] ?? [];
    $assets = collect($product['documents'] ?? []);
    $currencies = collect($lookups['currencies'] ?? []);
    $categoryList = collect($lookups['categories'] ?? []);
    $availability = $product['availability'] ?? [];
    $classification = array_values(array_filter([
        $product['main_category'] ?? '',
        $categoryList->firstWhere('id', $product['parent_id'] ?? 0)['name'] ?? '',
        $product['sub_category'] ?? '',
    ], static fn ($v) => trim((string) $v) !== ''));
    $canEditAnySection = collect(['supplier_pricing','client_pricing','options','documents','shipment','availability'])
        ->contains(fn ($section) => $perm($section, 'edit'));
    $created = filled($product['created_at'] ?? '') ? \Illuminate\Support\Carbon::parse($product['created_at']) : null;
    $updated = filled($product['updated_at'] ?? '') ? \Illuminate\Support\Carbon::parse($product['updated_at']) : null;
@endphp
<div class="ft-product-page ft-product-view-page">
    <div class="ft-product-page-breadcrumb">
        <a href="{{ route('supplier.products.module', ['group' => 'product']) }}">Products</a>
        <span>/</span><strong>{{ $product['display_code'] ?? '' }}</strong>
    </div>
    <header class="ft-product-detail-header">
        <div>
            <h1>{{ $product['name'] ?? '' }}</h1>
            <div class="ft-product-detail-meta">
                <span class="ft-product-original-status {{ ($product['status'] ?? '') === 'active' ? 'is-active' : '' }}">{{ ucfirst((string) ($product['status'] ?? '')) }}</span>
                <span>Product code {{ $product['display_code'] ?? '' }}</span>
                <span>Updated {{ $updated?->format('M j, Y') ?? '—' }}</span>
                <span>Created by {{ $product['created_by'] ?? '—' }}</span>
            </div>
        </div>
        <div class="ft-product-detail-actions">
            <a class="ft-product-page-btn is-secondary" href="{{ route('supplier.products.module', ['group'=>'product']) }}">Back to products</a>
            @if($perm('products', 'edit') || $canEditAnySection)
                <a class="ft-product-page-btn is-primary" href="{{ route('supplier.products.module', ['group'=>'product','id'=>$recordId,'edit'=>1]) }}">Edit product</a>
            @endif
            <details class="ft-native-product-action-menu">
                <summary class="ft-product-action-trigger" aria-label="Product actions" title="Product actions"><span></span><span></span><span></span></summary>
                <div class="ft-native-product-action-items">
                    <a href="{{ route('supplier.products.module', ['group'=>'product','id'=>$recordId]) }}">View product</a>
                    @if($perm('products', 'edit') || $canEditAnySection)
                        <a href="{{ route('supplier.products.module', ['group'=>'product','id'=>$recordId,'edit'=>1]) }}">Edit product</a>
                    @endif
                    @if($perm('products', 'delete'))
                        <form method="POST" action="{{ route('supplier.products.module.action') }}" onsubmit="return confirm('Delete this product? This removes it from the active catalogue.')">
                            @csrf
                            <input type="hidden" name="operation" value="delete_product"><input type="hidden" name="id" value="{{ $recordId }}"><input type="hidden" name="group" value="product">
                            <button type="submit">Delete product</button>
                        </form>
                    @endif
                </div>
            </details>
        </div>
    </header>

    <section class="ft-product-page-section">
        <div class="ft-product-page-section-heading"><div><h2>Product details</h2></div></div>
        <div class="ft-product-page-section-body">
            <div class="ft-product-detail-grid">
                <dl class="ft-product-detail-list">
                    <div><dt>Product code</dt><dd>{{ $product['display_code'] ?? '' }} <button type="button" class="ft-copy-btn" data-native-copy="{{ $product['display_code'] ?? '' }}" aria-label="Copy product code">⧉</button></dd></div>
                    <div><dt>Reference product code</dt><dd>{{ ($product['reference_code'] ?? '') ?: '—' }}</dd></div>
                    <div><dt>Product name</dt><dd>{{ $product['name'] ?? '' }}</dd></div>
                    <div><dt>Product size</dt><dd class="ft-product-detail-size">{{ ($product['size'] ?? '') ?: '—' }}</dd></div>
                    <div class="ft-product-detail-supplier-row"><dt>Tagged suppliers</dt><dd class="ft-product-detail-supplier-capsules">
                        <span class="ft-product-detail-supplier-capsule">{{ $company->flowtrack_short_code ?: $company->name }} <small>Assigned</small></span>
                    </dd></div>
                </dl>
                <div class="ft-product-detail-image-panel"><div class="ft-product-detail-image">
                        @if($product['has_image'] ?? false)
                            <img src="{{ route('supplier.products.module.asset', ['product'=>$recordId,'kind'=>'image']) }}" alt="{{ $product['name'] ?? 'Product' }}">
                        @else<span class="ft-product-image-placeholder">No image</span>@endif
                    </div>
                    @if($product['has_image'] ?? false)
                        <a href="{{ route('supplier.products.module.asset', ['product'=>$recordId,'kind'=>'image']) }}" target="_blank" rel="noopener">View full image</a>
                    @endif
                    </div>
            </div>
        </div>
    </section>

    <section class="ft-product-page-section">
        <div class="ft-product-page-section-heading"><div><h2>Classification &amp; availability</h2></div></div>
        <div class="ft-product-page-section-body">
            <div class="ft-product-classification-row">
                @forelse($classification as $i => $label)
                    <div class="ft-product-classification-step"><small>{{ ['Main category','Product category','Subcategory'][$i] ?? 'Category' }}</small><span class="ft-product-chip">{{ $label }}</span></div>
                    @unless($loop->last)<span class="ft-product-classification-arrow">›</span>@endunless
                @empty
                    <span>—</span>
                @endforelse
            </div>
            @if($perm('availability'))
                <div class="ft-product-availability-detail">
                    <div><strong>Client availability</strong><span>{{ ($availability['mode'] ?? 'all') === 'specific' ? 'Selected clients' : 'All clients' }}</span><small>{{ ($availability['mode'] ?? 'all') === 'specific' ? 'Only these clients can find and use this product.' : 'All active clients can find and use this product.' }}</small></div>
                    <div class="ft-product-client-badges">
                        @foreach(($availability['labels'] ?? []) as $label)<span>{{ $label }}</span>@endforeach
                        @if(($availability['mode'] ?? 'all') === 'specific')<em>{{ count($availability['labels'] ?? []) }} clients</em>@endif
                    </div>
                </div>
            @endif
        </div>
    </section>

    @if($perm('supplier_pricing'))
        <section class="ft-product-page-section">
            <div class="ft-product-page-section-heading"><div><h2>Supplier-based pricing</h2></div></div>
            <div class="ft-product-page-section-body">
                <p class="ft-price-section-hint">Select a supplier to review its price tables. Each currency is kept separate; additional tables do not replace primary prices.</p>
                <div class="ft-price-suppliers">
                    <details class="ft-price-supplier" open>
                        <summary class="ft-price-supplier-heading">
                            <span class="ft-price-supplier-identity">
                                <strong>{{ $company->name }}</strong>
                                <small>{{ count($price['rows'] ?? []) }} primary quantity {{ count($price['rows'] ?? []) === 1 ? 'tier' : 'tiers' }}</small>
                            </span>
                            <span class="ft-price-supplier-meta">
                                <span class="ft-price-badge">{{ $price['currency'] ?? 'USD' }} · Primary</span>
                                @if(count($price['additional_tables'] ?? []))
                                    <span class="ft-price-badge is-muted">+{{ count($price['additional_tables']) }} {{ count($price['additional_tables']) === 1 ? 'currency' : 'currencies' }}</span>
                                @endif
                                <span class="ft-price-chevron" aria-hidden="true"></span>
                            </span>
                        </summary>
                        <div class="ft-price-supplier-body">
                            @include('portal.product-native.product-currency-readonly', [
                                'currency' => $price['currency'] ?? 'USD', 'rows' => $price['rows'] ?? [],
                                'charges' => $price['charges'] ?? [], 'primary' => true,
                            ])
                            @foreach(($price['additional_tables'] ?? []) as $extraTable)
                                @include('portal.product-native.product-currency-readonly', [
                                    'currency' => $extraTable['currency'], 'rows' => $extraTable['rows'] ?? [],
                                    'charges' => $extraTable['charges'] ?? [], 'primary' => false,
                                ])
                            @endforeach
                            @if($perm('supplier_pricing','edit'))
                                <div class="ft-price-supplier-toolbar">
                                    <a class="ft-product-page-btn is-secondary" href="{{ route('supplier.products.module', ['group'=>'product','id'=>$recordId,'edit'=>1]) }}#product-pricing">Edit supplier pricing</a>
                                </div>
                            @endif
                        </div>
                    </details>
                </div>
            </div>
        </section>
    @endif

    @if($perm('client_pricing'))
        <section class="ft-product-page-section">
            <div class="ft-product-page-section-heading"><div><h2>Client pricing</h2></div></div>
            <div class="ft-product-page-section-body">
                @if(($availability['mode'] ?? 'all') !== 'specific')
                    <div class="ft-product-price-preview-wrap ft-product-detail-price-wrap" style="margin-bottom:14px">
                        <strong>All clients · {{ $clientPrice['currency'] ?? 'USD' }}</strong>
                        @include('portal.product-native.product-price-matrix-readonly', ['rows' => $clientPrice['rows'] ?? [], 'supplierMatrix' => false])
                    </div>
                @endif
                @foreach(($clientPrice['client_rows'] ?? []) as $clientRow)
                    <div class="ft-product-price-preview-wrap ft-product-detail-price-wrap" style="margin-bottom:14px">
                        <strong>{{ collect($lookups['clients'] ?? [])->firstWhere('id', $clientRow['client_id'])['name'] ?? ('Client #'.$clientRow['client_id']) }} · {{ $clientRow['currency'] ?? 'USD' }}</strong>
                        @include('portal.product-native.product-price-matrix-readonly', ['rows' => $clientRow['rows'] ?? [], 'supplierMatrix' => false])
                    </div>
                @endforeach
                @if(($availability['mode'] ?? 'all') === 'specific' && empty($clientPrice['client_rows']))
                    <div class="ft-product-price-preview-wrap ft-product-detail-price-wrap">
                        <strong>Selected clients · {{ $clientPrice['currency'] ?? 'USD' }}</strong>
                        @include('portal.product-native.product-price-matrix-readonly', ['rows' => [], 'supplierMatrix' => false])
                    </div>
                @endif
            </div>
        </section>
    @endif

    @if(($perm('options') && !empty($product['options'])) || ($perm('shipment') && !empty($shipment['urgencies'])))
        <div class="ft-product-options-shipping-grid">
            @if($perm('options') && !empty($product['options']))
                <section class="ft-product-page-section ft-product-options-section ft-product-options-detail-section">
                    <div class="ft-product-page-section-heading"><div><h2>Product options</h2></div></div>
                    <div class="ft-product-page-section-body"><div class="ft-product-option-detail-list">
                        @foreach($product['options'] as $option)
                            <div class="ft-product-option-detail-row">
                                <strong>{{ $option['label'] }}</strong>
                                <div class="ft-product-option-detail-charge"><small>Extra charge</small><span>{{ number_format((float) ($option['extra_charge'] ?? 0), 2) }}</span></div>
                                <div class="ft-product-option-detail-image">
                                    @if($option['has_image'] ?? false)
                                        <img alt="{{ $option['label'] }}" src="{{ route('supplier.products.module.asset', ['product'=>$recordId,'kind'=>'option_image','option_key'=>$option['key']]) }}">
                                    @else<span>No image</span>@endif
                                </div>
                            </div>
                        @endforeach
                    </div></div>
                </section>
            @endif
            @if($perm('shipment') && !empty($shipment['urgencies']))
                <section class="ft-product-page-section ft-product-shipping-detail-section">
                    <div class="ft-product-page-section-heading"><div><h2>Shipping urgencies</h2></div></div>
                    <div class="ft-product-page-section-body"><div class="ft-product-shipping-detail-table-wrap"><table class="ft-product-shipping-detail-table">
                        <thead><tr><th>Shipping urgency</th><th>Code</th><th>Extra charge</th></tr></thead>
                        <tbody>@foreach($shipment['urgencies'] as $urgency)
                            <tr><td><strong>{{ ($urgency['shipment_urgency_name'] ?? '') ?: ($urgency['shipment_urgency_code'] ?? '') }}</strong></td><td>{{ ($urgency['shipment_urgency_code'] ?? '') ?: '—' }}</td><td>{{ number_format((float) ($urgency['extra_charge'] ?? 0), 2) }}</td></tr>
                        @endforeach</tbody>
                    </table></div></div>
                </section>
            @endif
        </div>
    @endif

    @if($perm('documents'))
        <section class="ft-product-page-section ft-product-documents-section">
            <div class="ft-product-page-section-heading"><div><h2>Certificates &amp; documents</h2></div></div>
            <div class="ft-product-page-section-body">
                <div class="ft-product-documents-grid">
                    <div class="ft-product-certificate-number"><small>Test certificate number</small><strong>{{ ($product['test_certificate_number'] ?? '') ?: '—' }}</strong></div>
                    @foreach(['certificate' => 'Certificate & Test Report', 'template' => 'Product template'] as $kind => $label)
                        <div class="ft-product-document-card"><strong>{{ $label }}</strong>
                            @if($assets->firstWhere('kind', $kind))
                                <a href="{{ route('supplier.products.module.asset', ['product'=>$recordId,'kind'=>$kind]) }}">{{ $assets->firstWhere('kind', $kind)['label'] }}</a>
                            @else<span>No file uploaded</span>@endif
                        </div>
                    @endforeach
                </div>
                <footer class="ft-product-audit-row">
                    <span>Created {{ $created?->format('M j, Y') ?? '—' }} by {{ $product['created_by'] ?? '—' }} <b>·</b> Last updated {{ $updated?->format('M j, Y') ?? '—' }}</span>
                    <details class="ft-native-product-activity"><summary>View activity</summary><div class="ft-product-activity-popover"><strong>Product activity</strong><span>Created {{ $created?->format('M j, Y g:i A') ?? '—' }} by {{ $product['created_by'] ?? '—' }}</span><span>Last updated {{ $updated?->format('M j, Y g:i A') ?? '—' }}</span></div></details>
                </footer>
            </div>
        </section>
    @endif


</div>
