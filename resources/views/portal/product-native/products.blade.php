@php
    $rows = $catalog['products'] ?? [];
    $categories = collect($catalog['categories'] ?? []);
    $mainCategories = $categories->where('type', 'product_main_category');
    $productCategories = $categories->where('type', 'product_category');
    $filters = request()->only(['q', 'category_id', 'main_category', 'availability', 'status', 'per_page']);
    $page = (int) ($catalog['page'] ?? 1);
    $lastPage = (int) ($catalog['last_page'] ?? 1);
    $total = (int) ($catalog['total'] ?? 0);
    $perPage = (int) ($catalog['per_page'] ?? 20);
    $first = $total ? ($page - 1) * $perPage + 1 : 0;
    $last = min($total, $page * $perPage);
    $canDocuments = (bool) data_get($permissions, 'documents.view', false);
    $canAvailability = (bool) data_get($permissions, 'availability.view', false);
@endphp
<div class="ft-product-list-head">
    <div>
        <h1>Products</h1>
        <p>Manage the product catalog, client availability and supporting documents.</p>
    </div>
    <div class="ft-product-list-head-actions">
        @if(data_get($permissions, 'products.create'))
            <a class="ft-product-add-button" href="{{ route('supplier.products.module', ['group' => 'product', 'create' => 1]) }}">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                <span>Add product</span>
            </a>
        @endif
    </div>
</div>
<section class="ft-product-list-shell">
    <form class="ft-product-filter-card" method="GET" action="{{ route('supplier.products.module') }}">
        <input type="hidden" name="group" value="product">
        <label class="ft-product-search">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" aria-hidden="true"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
            <input type="search" name="q" maxlength="100" value="{{ request('q', '') }}" placeholder="Search product name, product code or reference code" aria-label="Search products">
        </label>
        @if($mainCategories->isNotEmpty())
            <label class="ft-product-filter-select">
                <select name="main_category" aria-label="Main category" onchange="this.form.requestSubmit()">
                    <option value="">All main categories</option>
                    @foreach($mainCategories as $category)
                        <option value="{{ $category['name'] }}" @selected(request('main_category') === $category['name'])>{{ $category['name'] }}</option>
                    @endforeach
                </select>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            </label>
        @endif
        @if($productCategories->isNotEmpty())
            <label class="ft-product-filter-select">
                <select name="category_id" aria-label="Product category" onchange="this.form.requestSubmit()">
                    <option value="">All product categories</option>
                    @foreach($productCategories as $category)
                        <option value="{{ $category['id'] }}" @selected((int) request('category_id', 0) === (int) $category['id'])>{{ $category['name'] }}</option>
                    @endforeach
                </select>
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
            </label>
        @endif
        <label class="ft-product-filter-select">
            <select aria-label="Supplier" disabled><option>{{ $company->name }}</option></select>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" aria-hidden="true"><path d="m6 9 6 6 6-6"/></svg>
        </label>
        @if($canAvailability)
            <label class="ft-product-filter-select"><select name="availability" aria-label="Client availability" onchange="this.form.requestSubmit()">
                <option value="">All client availability</option>
                <option value="all" @selected(request('availability') === 'all')>All clients</option>
                <option value="specific" @selected(request('availability') === 'specific')>Specific clients</option>
            </select><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m6 9 6 6 6-6"/></svg></label>
        @endif
        <label class="ft-product-filter-select"><select name="status" aria-label="Status" onchange="this.form.requestSubmit()">
            <option value="">All statuses</option>
            <option value="active" @selected(request('status') === 'active')>Active</option>
            <option value="inactive" @selected(request('status') === 'inactive')>Inactive</option>
        </select><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="m6 9 6 6 6-6"/></svg></label>
        <button class="ft-product-clear" type="submit">Search</button>
        <a class="ft-product-clear" href="{{ route('supplier.products.module', ['group' => 'product']) }}">Clear</a>
    </form>
    <div class="ft-product-result-count">Showing {{ $first }}–{{ $last }} of {{ number_format($total) }} products</div>
    <div class="ft-product-table-card">
        <div class="ft-product-table-scroll">
            <table class="ft-product-list-table">
                <thead><tr>
                    <th>Product</th><th>Product code</th><th>Classification</th><th>Supplier</th><th>Size</th>
                    @if($canAvailability)<th>Availability</th>@endif
                    @if($canDocuments)<th>Documents</th>@endif
                    <th>Status</th><th>Updated</th><th class="ft-product-actions-heading" aria-label="Actions"></th>
                </tr></thead>
                <tbody>
                @forelse($rows as $record)
                    <tr>
                        <td><div class="ft-product-name-cell">
                            <div class="ft-product-list-thumb">
                                @if($canDocuments && ($record['has_image'] ?? false))
                                    <img src="{{ route('supplier.products.module.asset', ['product' => $record['id'], 'kind' => 'image']) }}" alt="{{ $record['name'] }}" loading="lazy">
                                @else
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.55" aria-hidden="true"><path d="M20 12 12 20 4 12V4h8l8 8Z"/><circle cx="8.5" cy="8.5" r="1.2"/></svg>
                                @endif
                            </div>
                            <a class="ft-product-name-link" href="{{ route('supplier.products.module', ['group' => 'product', 'id' => $record['id']]) }}" title="Open {{ $record['name'] }} details">{{ $record['name'] }}</a>
                        </div></td>
                        <td><div class="ft-product-code-cell"><strong>{{ $record['code'] }}</strong><span>Ref: {{ $record['reference_code'] ?: '—' }}</span></div></td>
                        <td><div class="ft-product-classification"><strong>{{ $record['main_category'] ?: '—' }}</strong><span>{{ ($record['classification_path'] ?? '') ?: '—' }}</span></div></td>
                        <td><span class="ft-product-supplier-capsule">{{ $company->name }} <small>Assigned</small></span></td>
                        <td><span class="ft-product-size">{{ ($record['size'] ?? '') ?: '—' }}</span></td>
                        @if($canAvailability)
                            <td><div class="ft-product-availability">@forelse(($record['availability_labels'] ?? []) as $label)<span>{{ $label }}</span>@empty<span>{{ ($record['availability_mode'] ?? 'all') === 'specific' ? 'Selected clients' : 'All clients' }}</span>@endforelse</div></td>
                        @endif
                        @if($canDocuments)
                            <td><div class="ft-product-documents">@if(($record['document_count'] ?? 0) > 0)
                                <div class="ft-product-document-count"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M6 2h8l4 4v16H6zM14 2v5h5M8.5 13h7"/></svg><span>{{ $record['document_count'] }} {{ \Illuminate\Support\Str::plural('file', (int) $record['document_count']) }}</span></div>
                                <small>{{ \Illuminate\Support\Str::limit((string) ($record['first_document_label'] ?? ''), 18) }}</small>
                            @else<span class="ft-product-documents-empty">—</span>@endif</div></td>
                        @endif
                        <td><span class="ft-product-original-status {{ $record['status'] === 'active' ? 'is-active' : '' }}">{{ ucfirst($record['status']) }}</span></td>
                        <td><span class="ft-product-updated">{{ isset($record['updated_at']) ? \Illuminate\Support\Carbon::parse($record['updated_at'])->diffForHumans() : '—' }}</span></td>
                        <td class="ft-product-actions-cell"><div class="ft-product-original-actions">
                            <a href="{{ route('supplier.products.module', ['group' => 'product', 'id' => $record['id']]) }}">View</a>
                            @if(data_get($permissions, 'products.edit'))<a href="{{ route('supplier.products.module', ['group' => 'product', 'id' => $record['id'], 'edit' => 1]) }}">Edit</a>@endif
                        </div></td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 8 + (int) $canAvailability + (int) $canDocuments }}"><div class="empty-state">No products found.</div></td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="ft-product-pagination">
            <div class="ft-product-pagination-left">
                <div class="ft-product-result-count ft-product-result-count-footer">Showing {{ $first }}–{{ $last }} of {{ number_format($total) }} products</div>
                <form method="get" class="ft-product-rows-per-page" action="{{ route('supplier.products.module') }}">
                    @foreach($filters as $key => $value)
                        @if($key !== 'per_page' && is_scalar($value))<input type="hidden" name="{{ $key }}" value="{{ $value }}">@endif
                    @endforeach
                    <input type="hidden" name="group" value="product"><span>Rows per page</span>
                    <select name="per_page" aria-label="Rows per page" onchange="this.form.requestSubmit()">
                        @foreach([10,20,50,100] as $n)<option value="{{ $n }}" @selected($perPage === $n)>{{ $n }}</option>@endforeach
                    </select>
                </form>
            </div>
            <div class="ft-product-page-position">Page {{ $page }} of {{ $lastPage }}</div>
            <div class="ft-product-page-buttons">
                @if($page > 1)<a href="{{ route('supplier.products.module', ['group'=>'product','page'=>$page - 1] + $filters) }}">Previous</a>@else<span class="is-disabled">Previous</span>@endif
                @for($n = max(1,$page-1); $n <= min($lastPage,$page+1); $n++)
                    <a class="{{ $n === $page ? 'is-current' : '' }}" href="{{ route('supplier.products.module', ['group'=>'product','page'=>$n] + $filters) }}">{{ $n }}</a>
                @endfor
                @if($page < $lastPage)<a href="{{ route('supplier.products.module', ['group'=>'product','page'=>$page+1] + $filters) }}">Next</a>@else<span class="is-disabled">Next</span>@endif
            </div>
        </div>
    </div>
</section>
