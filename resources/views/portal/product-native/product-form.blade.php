@php
    $product = $details ?: [];
    $categories = collect($lookups['categories'] ?? []);
    $productCategories = $categories->where('type','product_category');
    $mainCategories = $categories->where('type','product_main_category');
    $formId = $edit ? ($product['id'] ?? 0) : 0;
    $canSaveProduct = ! $edit || (bool) data_get($permissions, 'products.edit', false);
    $productFieldDisabled = $canSaveProduct ? '' : 'disabled';
@endphp
<div class="ft-product-page ft-product-form-page ft-form-standard ft-form-standard--product">
    <div class="ft-product-page-breadcrumb">
        <a href="{{ route('supplier.products.module', ['group'=>'product']) }}">Products</a>
        <span>/</span><strong>{{ $edit ? 'Edit product' : 'Create product' }}</strong>
    </div>
    <header class="ft-product-form-header">
        <div>
            <h1>{{ $edit ? 'Edit product' : 'Create product' }}</h1>
            <p>{{ $edit ? 'Update the product information, linked suppliers, availability and supporting documents.' : 'Add a product and link its category, suppliers, image and supporting documents.' }}</p>
        </div>
        @if($edit && $canSaveProduct)
            <div class="ft-product-form-top-actions">
                <a class="ft-product-page-btn is-secondary" href="{{ route('supplier.products.module', ['group'=>'product','id'=>$formId]) }}">Cancel</a>
                <button class="ft-product-page-btn is-primary" type="submit" form="supplier-native-product-form">Save changes</button>
            </div>
        @endif
    </header>
    @if($edit && data_get($permissions, 'availability.edit', false))
        <form id="supplier-native-availability-form" method="POST" action="{{ route('supplier.products.module.action') }}">
            @csrf
            <input type="hidden" name="operation" value="save_pricing"><input type="hidden" name="group" value="product"><input type="hidden" name="id" value="{{ $formId }}"><input type="hidden" name="section" value="availability">
        </form>
    @endif
    @if($edit && data_get($permissions, 'documents.edit', false))
        <form id="supplier-native-image-upload" method="POST" action="{{ route('supplier.products.module.action') }}" enctype="multipart/form-data">
            @csrf
            <input type="hidden" name="operation" value="upload_asset"><input type="hidden" name="group" value="product"><input type="hidden" name="id" value="{{ $formId }}"><input type="hidden" name="kind" value="image">
        </form>
    @endif
    <div class="ft-product-form-shell">
    <form id="supplier-native-product-form" method="POST" action="{{ route('supplier.products.module.action') }}">
        @csrf
        <input type="hidden" name="operation" value="save_product">
        <input type="hidden" name="group" value="product">
        <input type="hidden" name="id" value="{{ $formId }}">
        <section class="ft-product-page-section">
            <div class="ft-product-page-section-heading"><span class="ft-product-page-section-number">1</span><div><h2>Product information</h2></div></div>
            <div class="ft-product-page-section-body">
                <div class="ft-product-form-info-grid">
                    <div class="ft-product-form-fields">
                        <div class="ft-form-grid ft-form-grid-3">
                            <label class="ft-product-field"><span>Product code</span><div class="ft-product-locked-field">{{ $product['display_code'] ?? 'Generated after creation' }} <span>⌑</span></div><small>Generated automatically after the product is created.</small></label>
                            <label class="ft-product-field"><span>Reference product code <em>Optional</em></span><input @disabled(!$canSaveProduct) name="reference_code" maxlength="120" value="{{ old('reference_code', $product['reference_code'] ?? '') }}" placeholder="Client or supplier reference"><small>Client or supplier reference used for search and matching.</small></label>
                            <label class="ft-product-field"><span>Product name <i>*</i></span><input @disabled(!$canSaveProduct) name="name" required maxlength="240" value="{{ old('name', $product['name'] ?? '') }}" placeholder="Enter product name"></label>
                        </div>
                        <div class="ft-product-field ft-product-default-supplier"><span>Default supplier <em>Assigned</em></span><div class="ft-product-locked-field">{{ $company->flowtrack_short_code ?: $company->name }}</div><small>This product remains assigned to your supplier account in FlowTracker.</small></div>
                        <div class="ft-product-field ft-product-linked-suppliers"><span>Linked suppliers</span><div class="ft-product-locked-field">{{ $company->flowtrack_short_code ?: $company->name }}</div><small>A supplier can view and edit only products that FlowTracker has tagged to its account.</small></div>
                        <label class="ft-product-field ft-product-size-field"><span>Product size</span><textarea @disabled(!$canSaveProduct) name="size" rows="4" placeholder="Add size/specification details. Use a new line for each item, e.g. width, finished length, material, capacity or dimensions.">{{ old('size', $product['size'] ?? '') }}</textarea><small>Enter multiple size/specification details on separate lines so the information stays easy to read.</small></label>
                        @include('portal.product-native.product-form-availability')
                        <input type="hidden" name="description" value="{{ old('description', $product['description'] ?? '') }}">
                        <input type="hidden" name="status" value="{{ old('status', $product['status'] ?? 'active') }}">
                    </div>
                    <div class="ft-product-image-column">
                        <span class="ft-product-field-title">Product image <em>Optional</em></span>
                        <div class="ft-product-image-drop ft-native-image-preview" @if($edit && data_get($permissions, 'documents.edit', false)) data-native-image-drop @endif>
                            @if($edit && ($product['has_image'] ?? false) && data_get($permissions, 'documents.view'))
                                <img src="{{ route('supplier.products.module.asset', ['product'=>$formId,'kind'=>'image']) }}" alt="{{ $product['name'] }}">
                            @else
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" width="32" height="32" aria-hidden="true"><rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="8" cy="9" r="2"/><path d="m3 17 6-6 4 4 3-3 5 5"/></svg>
                                <strong>{{ $edit && data_get($permissions, 'documents.edit', false) ? 'Drop image or browse' : 'No image' }}</strong>
                            @endif
                        </div>
                        @if($edit && data_get($permissions, 'documents.edit', false))
                            <div class="ft-ux-image-upload-actions">
                                <label class="ft-product-field ft-native-image-input"><span>Choose image</span><input type="file" name="file" form="supplier-native-image-upload" id="supplierNativeImageFile" accept="image/png,image/jpeg,image/webp" required></label>
                                <button type="submit" form="supplier-native-image-upload" class="ft-product-page-btn is-secondary">Upload image</button>
                            </div>
                        @endif
                        <small class="ft-product-help">PNG, JPG or WEBP · Max 5 MB{{ !$edit ? ' · Save the product before uploading.' : '' }}</small>
                    </div>
                </div>
            </div>
        </section>
        <section class="ft-product-page-section">
            <div class="ft-product-page-section-heading"><span class="ft-product-page-section-number">2</span><div><h2>Category hierarchy</h2><p>Select from top to bottom. Each list is filtered by the selection above.</p></div></div>
            <div class="ft-product-page-section-body">
                <div class="ft-form-grid ft-form-grid-3">
                    <label class="ft-product-field"><span>Main category</span><select @disabled(!$canSaveProduct) id="nativeProductMainCategory" aria-label="Main category"><option value="">Select main category</option>@foreach($mainCategories as $category)<option value="{{ $category['id'] }}" @selected((int) ($productCategories->firstWhere('id', (int) ($product['parent_id'] ?? 0))['main_category_id'] ?? 0) === (int) $category['id'])>{{ $category['name'] }}</option>@endforeach</select></label>
                    <label class="ft-product-field"><span>Product category</span><select @disabled(!$canSaveProduct) name="parent_id" id="nativeProductCategory"><option value="">Select category</option>@foreach($productCategories as $category)<option value="{{ $category['id'] }}" data-main-id="{{ $category['main_category_id'] ?? 0 }}" @selected((int) old('parent_id', $product['parent_id'] ?? 0) === (int) $category['id'])>{{ $category['name'] }}</option>@endforeach</select></label>
                    <label class="ft-product-field"><span>Subcategory</span><input @disabled(!$canSaveProduct) name="sub_category" maxlength="200" value="{{ old('sub_category', $product['sub_category'] ?? '') }}" placeholder="Optional subcategory"></label>
                </div>
            </div>
        </section>
    </form>
    @if($edit)
        @include('portal.product-native.product-form-extra-sections')
    @else
        @foreach(['3'=>'Product pricing', '4'=>'Product options', '5'=>'Shipping urgencies', '6'=>'Supporting documents'] as $number => $heading)
            <section class="ft-product-page-section">
                <div class="ft-product-page-section-heading"><span class="ft-product-page-section-number">{{ $number }}</span><div><h2>{{ $heading }}</h2></div></div>
                <div class="ft-product-page-section-body"><small>Save the product first to manage this section through the secure FlowTracker API.</small></div>
            </section>
        @endforeach
    @endif
    <footer class="ft-product-form-bottom-actions ft-ux-product-form-footer">
        <small>{{ $edit ? 'Product information and category are saved here. Shipment settings and document uploads have separate save actions.' : 'Required fields are marked with an asterisk.' }}</small>
        <div class="ft-ux-footer-buttons">
            <a class="ft-product-page-btn is-secondary" href="{{ route('supplier.products.module', $formId ? ['group'=>'product','id'=>$formId] : ['group'=>'product']) }}">Cancel</a>
            @if($canSaveProduct)
                <button class="ft-product-page-btn is-primary" type="submit" form="supplier-native-product-form">{{ $edit ? 'Save changes' : 'Create product' }}</button>
            @endif
        </div>
    </footer>
    </div>
</div>
