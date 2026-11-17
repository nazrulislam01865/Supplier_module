@php
  $categories = collect($catalog['categories'] ?? []);
  $editing = $id && $edit ? $categories->firstWhere('id', $id) : null;
  $categoryTypes = ['product_main_category' => 'Main category', 'product_category' => 'Product category', 'product_subcategory' => 'Subcategory'];
@endphp
@if($create || $edit)
  <div class="ft-native-breadcrumb"><a href="{{ route('supplier.products.module', ['group'=>'product_category']) }}">Product Categories</a> <span>/</span> {{ $edit ? 'Edit category' : 'Create category' }}</div>
  <header class="ft-native-header"><div><h1>{{ $edit ? 'Edit Product Category' : 'Create Product Category' }}</h1><p>Maintain the same three-level category hierarchy used in FlowTracker.</p></div></header>
  @if(!$edit || $editing)
    <form action="{{ route('supplier.products.module.action') }}" method="post" class="ft-native-card ft-native-form-card">@csrf
      <input type="hidden" name="operation" value="save_category"><input type="hidden" name="group" value="product_category"><input type="hidden" name="id" value="{{ $editing['id'] ?? 0 }}">
      <div class="ft-native-grid ft-native-grid-2">
        <label><span>Category level *</span><select name="type" id="nativeCategoryLevel" required @disabled($edit)>
          @foreach($categoryTypes as $value=>$label)<option value="{{ $value }}" @selected(old('type', $editing['type'] ?? 'product_category') === $value)>{{ $label }}</option>@endforeach
        </select>@if($edit)<input type="hidden" name="type" value="{{ $editing['type'] ?? '' }}">@endif</label>
        <label><span>Parent category</span><select name="parent_id" id="nativeCategoryParent"><option value="">No parent</option>
          @foreach($categories as $cat)
            @if(in_array($cat['type'], ['product_main_category','product_category'], true))<option value="{{ $cat['id'] }}" data-parent-type="{{ $cat['type'] }}" @selected((int) old('parent_id', ($editing['type'] ?? '') === 'product_category' ? ($editing['main_category_id'] ?? 0) : ($editing['parent_id'] ?? 0)) === (int) $cat['id'])>{{ $cat['name'] }}</option>@endif
          @endforeach
        </select></label>
        <label><span>Category name *</span><input name="name" required maxlength="200" value="{{ old('name', $editing['name'] ?? '') }}" placeholder="Enter category name"></label>
        <label><span>Status</span><select name="status"><option value="active" @selected(old('status', $editing['status'] ?? 'active') === 'active')>Active</option><option value="inactive" @selected(old('status', $editing['status'] ?? 'active') === 'inactive')>Inactive</option></select></label>
      </div>
      <label><span>Description</span><textarea name="description" rows="3">{{ old('description', $editing['description'] ?? '') }}</textarea></label>
      <footer class="ft-native-form-actions"><a class="ft-native-btn is-secondary" href="{{ route('supplier.products.module', ['group'=>'product_category']) }}">Cancel</a><button class="ft-native-btn is-primary" type="submit">Save category</button></footer>
    </form>
  @endif
@else
  @php
      $allCategories = collect($catalog['categories'] ?? []);
      $counts = collect($catalog['product_counts'] ?? []);
      $search = mb_strtolower(trim((string) request('cat_q', '')));
      $level = (string) request('level', '');
      $status = (string) request('category_status', '');
      $filtered = $allCategories->filter(function ($cat) use ($search, $level, $status) {
          return ($search === '' || str_contains(mb_strtolower($cat['name'].' '.$cat['code']), $search))
              && ($level === '' || $level === $cat['type'])
              && ($status === '' || $status === $cat['status']);
      });
      $mainRows = $allCategories->where('type', 'product_main_category');
      $productRows = $allCategories->where('type', 'product_category');
      $subRows = $allCategories->where('type', 'product_subcategory');
      // Match FlowTracker's category-page sizes. Pagination happens after filtering,
      // without additional bridge requests or changing category edit lookup data.
      $categoryPerPage = (int) request('cat_per_page', 6);
      $categoryPerPage = in_array($categoryPerPage, [6, 10, 20, 50], true) ? $categoryPerPage : 6;
      $categoryTotal = $filtered->count();
      $categoryLastPage = max(1, (int) ceil($categoryTotal / $categoryPerPage));
      $categoryPage = min($categoryLastPage, max(1, (int) request('cat_page', 1)));
      $categoryFirst = $categoryTotal ? (($categoryPage - 1) * $categoryPerPage + 1) : 0;
      $categoryLast = min($categoryTotal, $categoryPage * $categoryPerPage);
      $pageCategories = $filtered->slice(($categoryPage - 1) * $categoryPerPage, $categoryPerPage);
      $canEditCategory = data_get($permissions, 'categories.edit', false);
      $canDeleteCategory = data_get($permissions, 'categories.delete', false);
  @endphp
  <div class="ft-category-page">
      <header class="ft-category-page-head"><div><h1>Product Categories</h1><p>Manage the hierarchy used to organize and find products.</p></div>
        <div class="ft-category-head-right">
          @if(data_get($permissions, 'categories.create'))
            <a class="ft-category-add" href="{{ route('supplier.products.module', ['group'=>'product_category','create'=>1]) }}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 5v14M5 12h14"/></svg><span>Add category</span></a>
          @endif
        </div>
      </header>
      <form class="ft-category-toolbar" method="get" action="{{ route('supplier.products.module') }}">
          <input type="hidden" name="group" value="product_category">
          <label class="ft-category-search"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg><input type="search" name="cat_q" maxlength="100" value="{{ request('cat_q','') }}" placeholder="Search category name or code" aria-label="Search category name or code"></label>
          <label class="ft-category-filter-select"><select name="level" aria-label="Category level" onchange="this.form.requestSubmit()"><option value="">Category level</option>
              @foreach($categoryTypes as $type=>$label)<option value="{{ $type }}" @selected($level === $type)>{{ $label }}</option>@endforeach
          </select></label>
          <label class="ft-category-filter-select"><select name="category_status" aria-label="Status" onchange="this.form.requestSubmit()"><option value="">All statuses</option><option value="active" @selected($status === 'active')>Active</option><option value="inactive" @selected($status === 'inactive')>Inactive</option></select></label>
          <button type="submit" class="ft-category-clear">Search</button><a class="ft-category-clear" href="{{ route('supplier.products.module',['group'=>'product_category']) }}">Clear</a>
      </form>
      <section class="ft-category-card">
          <div class="ft-category-table-scroll">
              <div class="ft-category-tree-grid ft-category-tree-grid-head"><span>Category</span><span>Code</span><span>Level</span><span>Parent</span><span>Products</span><span>Status</span><span>Updated</span><span></span></div>
              @forelse($pageCategories as $cat)
                  @php
                      $main = $cat['type'] === 'product_category'
                          ? $mainRows->firstWhere('id', $cat['main_category_id'] ?? 0)
                          : null;
                      $parent = $cat['type'] === 'product_subcategory'
                          ? $productRows->firstWhere('id', $cat['parent_id'] ?? 0)
                          : null;
                      $mainCount = $cat['type'] === 'product_main_category'
                          ? $productRows->where('main_category_id', $cat['id'])->sum(fn($c)=>(int) $counts->get($c['id'],0))
                          : ($cat['type'] === 'product_category' ? (int)$counts->get($cat['id'],0) : 0);
                  @endphp
                  <div class="ft-category-tree-grid ft-category-row {{ $cat['type'] === 'product_main_category' ? 'is-main' : '' }}">
                      <div class="ft-category-name {{ $cat['type'] === 'product_category' ? 'is-indent-1' : ($cat['type'] === 'product_subcategory' ? 'is-indent-2' : '') }}"><span class="ft-category-tag-icon">◇</span><strong>{{ $cat['name'] }}</strong></div>
                      <span>{{ $cat['code'] }}</span>
                      <span><b class="ft-category-level {{ $cat['type'] === 'product_main_category' ? 'is-main' : '' }}">{{ $categoryTypes[$cat['type']] ?? $cat['type'] }}</b></span>
                      <span class="ft-category-muted">{{ $main['name'] ?? $parent['name'] ?? '—' }}</span>
                      <strong>{{ $cat['type'] === 'product_subcategory' ? '—' : number_format($mainCount) }}</strong>
                      <span><b class="ft-category-status {{ $cat['status'] === 'active' ? '' : 'is-off' }}">{{ ucfirst($cat['status']) }}</b></span>
                      <span class="ft-category-updated">{{ filled($cat['updated_at'] ?? null) ? \Illuminate\Support\Carbon::parse($cat['updated_at'])->diffForHumans() : '—' }}</span>
                      <span class="ft-category-original-actions">
                        @if($canEditCategory)<a href="{{ route('supplier.products.module', ['group'=>'product_category','id'=>$cat['id'],'edit'=>1]) }}">Edit</a>@endif
                        @if($canDeleteCategory)<form method="post" action="{{ route('supplier.products.module.action') }}" onsubmit="return confirm('Delete this category? Categories in use are protected.')">@csrf<input type="hidden" name="group" value="product_category"><input type="hidden" name="operation" value="delete_category"><input type="hidden" name="id" value="{{ $cat['id'] }}"><button type="submit">Delete</button></form>@endif
                      </span>
                  </div>
              @empty
                  <div class="ft-category-empty">No categories found.</div>
              @endforelse
          </div>
          <footer class="ft-category-footer">
              <div class="ft-category-footer-left">
                  <span class="ft-category-footer-count">Showing <b>{{ $categoryFirst }}–{{ $categoryLast }}</b> of <b>{{ number_format($categoryTotal) }}</b> categories</span>
                  <form method="get" action="{{ route('supplier.products.module') }}" class="ft-category-page-size">
                      <input type="hidden" name="group" value="product_category">
                      <input type="hidden" name="cat_q" value="{{ request('cat_q', '') }}">
                      <input type="hidden" name="level" value="{{ $level }}">
                      <input type="hidden" name="category_status" value="{{ $status }}">
                      <label>Rows per page
                          <select name="cat_per_page" aria-label="Category rows per page" onchange="this.form.requestSubmit()">
                              @foreach([6, 10, 20, 50] as $size)<option value="{{ $size }}" @selected($categoryPerPage === $size)>{{ $size }}</option>@endforeach
                          </select>
                      </label>
                  </form>
              </div>
              <span>Page {{ $categoryPage }} of {{ $categoryLastPage }}</span>
              <form method="get" action="{{ route('supplier.products.module') }}" class="ft-category-pages" aria-label="Category pagination">
                  <input type="hidden" name="group" value="product_category">
                  <input type="hidden" name="cat_q" value="{{ request('cat_q', '') }}">
                  <input type="hidden" name="level" value="{{ $level }}">
                  <input type="hidden" name="category_status" value="{{ $status }}">
                  <input type="hidden" name="cat_per_page" value="{{ $categoryPerPage }}">
                  <button type="submit" name="cat_page" value="1" aria-label="First page" @disabled($categoryPage === 1)>|‹</button>
                  <button type="submit" name="cat_page" value="{{ max(1, $categoryPage - 1) }}" aria-label="Previous page" @disabled($categoryPage === 1)>‹</button>
                  @php
                      $pageStart = max(1, $categoryPage - 1);
                      $pageEnd = min($categoryLastPage, $pageStart + 3);
                      $pageStart = max(1, $pageEnd - 3);
                  @endphp
                  @for($pageNumber = $pageStart; $pageNumber <= $pageEnd; $pageNumber++)
                      <button type="submit" name="cat_page" value="{{ $pageNumber }}" @class(['is-active' => $pageNumber === $categoryPage]) @if($pageNumber === $categoryPage) aria-current="page" @endif>{{ $pageNumber }}</button>
                  @endfor
                  <button type="submit" name="cat_page" value="{{ min($categoryLastPage, $categoryPage + 1) }}" aria-label="Next page" @disabled($categoryPage === $categoryLastPage)>›</button>
                  <button type="submit" name="cat_page" value="{{ $categoryLastPage }}" aria-label="Last page" @disabled($categoryPage === $categoryLastPage)>›|</button>
              </form>
          </footer>
      </section>
  </div>
@endif
