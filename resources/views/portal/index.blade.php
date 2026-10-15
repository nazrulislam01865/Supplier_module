<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}"><meta name="supplier-base-path" content="{{ parse_url(config('app.url'), PHP_URL_PATH) ?: '' }}">@include('portal.partials.flowtrack-favicon')
<title>{{ $title ?? 'FlowTrack Supplier Portal' }}</title><link rel="stylesheet" href="{{ asset('assets/supplier.css') }}?v={{ filemtime(public_path('assets/supplier.css')) }}">    <link rel="stylesheet" href="{{ asset('assets/supplier-mobile.css') }}?v={{ filemtime(public_path('assets/supplier-mobile.css')) }}">
</head><body><!-- PORTAL VIEW -->
  <section id="portalView">
    <header class="topbar">
      <div class="topbar-left">
        <button class="btn btn-ghost btn-sm mobile-menu" id="mobileMenuBtn" aria-label="Open menu">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
        </button>
        <a class="brand" href="{{ route('supplier.home') }}?view=dashboard" aria-label="Go to dashboard">@include('portal.partials.flowtrack-brand')</a>
      </div>
      <div class="topbar-right">
        <button class="notification-btn" id="notificationBtn" aria-label="Notifications">
          <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
          <span class="notification-dot {{ $dashboard['unreadNotifications'] ? '' : 'hidden' }}" id="notificationDot"></span>
        </button>
        <div class="account">
          <div class="avatar">{{ mb_strtoupper(mb_substr($user->name, 0, 2)) }}</div>
          <div class="account-meta"><strong>{{ $user->name }}</strong><small>{{ $company->name }}</small></div>
          <button class="icon-button" id="accountBtn" aria-label="Account menu">
            <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 9 6 6 6-6"/></svg>
          </button>
        </div>
      </div>
    </header>

    <div class="shell">
      @include('portal.partials.sidebar')

      <main class="main">
        <div class="content">
          <!-- Dashboard (one partial for first load and realtime refresh) -->
          <section id="view-dashboard" @class(['app-view', 'hidden' => $initialView !== 'dashboard'])><div id="dashboardContent">
            @include('portal.partials.dashboard', $dashboard)
          </div></section>

          <!-- Quotations -->
          <section id="view-quotations" @class(['app-view', 'hidden' => $initialView !== 'quotations'])>
            <div class="page-header">
              <div><h1>RFQs & Quotations</h1><p>Review RFQ invitations, continue drafts, respond to revisions and track quotation outcomes.</p></div>
            </div>
            <div class="toolbar">
              <div class="tabs" id="quoteTabs">
                <button class="tab active" data-status="all">All <span class="mono">({{ $quotations->count() }})</span></button>
                <button class="tab" data-status="open">Open <span class="mono">({{ $quotations->where('status','open')->count() }})</span></button>
                <button class="tab" data-status="draft">Draft <span class="mono">({{ $quotations->where('status','draft')->count() }})</span></button>
                <button class="tab" data-status="submitted">Submitted <span class="mono">({{ $quotations->where('status','submitted')->count() }})</span></button>
              </div>
              <div class="search-wrap">
                <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/></svg>
                <input id="quoteSearch" placeholder="Search RFQ, product or reference" />
              </div>
            </div>
            <div class="table-card"><div class="table-scroll">
              <table>
                <thead><tr><th>Product / RFQ</th><th>Buyer</th><th>Quantity</th><th>Due</th><th>Status</th><th>Last updated</th><th style="text-align:right">Action</th></tr></thead>
                <tbody id="quoteTableBody">@forelse($quotations as $rfq)
                  <tr><td><strong>{{ $rfq['product'] }}</strong><br><small>{{ $rfq['ref'] }}</small></td><td>{{ $rfq['buyer'] }}</td><td>{{ $rfq['qty'] }}</td><td>{{ $rfq['dueShort'] }}</td><td>{{ ucfirst($rfq['status']) }}</td><td>{{ $rfq['updated'] }}</td><td><button type="button" class="btn btn-outline-teal btn-sm" data-open-rfq="{{ $rfq['id'] }}">View</button></td></tr>
                  @empty<tr><td colspan="7">No RFQs have been assigned to this supplier.</td></tr>@endforelse</tbody>
              </table>
            </div></div>
          </section>

          <!-- RFQ Detail -->
          <section id="view-rfq" class="app-view hidden">
            <button class="back-link" id="backToQuotes"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m15 18-6-6 6-6"/></svg>Back to quotations</button>
            <div class="page-header">
              <div><div class="eyebrow" style="margin-bottom:4px">Quotation request</div><h1 id="rfqTitle">RFQ details</h1><p id="rfqRef">Select an assigned RFQ</p></div>
              <div class="header-actions"><span id="rfqStatusBadge" class="badge badge-teal">Open</span></div>
            </div>

            <div class="detail-layout">
              <div class="detail-stack">
                <div class="info-strip"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/></svg><div>This request is scoped to your authenticated supplier company. Review the buyer requirements before starting your quotation.</div></div>

                <div class="card">
                  <div class="card-header"><div><h2>RFQ details</h2><p>Buyer request and quotation timing</p></div></div>
                  <div class="detail-grid">
                    <div class="detail-cell"><small>Inquiry reference</small><strong>--</strong></div>
                    <div class="detail-cell"><small>Supplier</small><strong>{{ $company->name }}</strong></div>
                    <div class="detail-cell"><small>Contact person</small><strong>{{ $user->name }}</strong></div>
                    <div class="detail-cell"><small>Requested quantity</small><strong id="rfqQty">--</strong></div>
                    <div class="detail-cell"><small>Quotation due</small><strong id="rfqDue">{{ $quotations->first()['due'] ?? 'No deadline' }}</strong></div>
                    <div class="detail-cell"><small>Requested delivery date</small><strong id="rfqDeliveryDate">Not specified</strong></div>
                  </div>
                </div>

                <div class="card">
                  <div class="card-header"><div><h2>Product requirements</h2><p>Product supplied with this quotation request</p></div></div>
                  <div class="card-body">
                    <div class="product-detail">
                      <div class="product-thumb" id="rfqThumb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M5 9c4-2 10-2 14 0v7c-4 2-10 2-14 0V9Z"/><path d="M5 11 2 9M19 11l3-2M8 11h8M8 14h8"/></svg></div>
                      <div><h3 id="rfqProductName">Select a requested product</h3><div class="ref" id="rfqProductCode">--</div>
                        <div class="product-specs" id="rfqProductItems"></div>
                      </div>
                    </div>
                  </div>
                </div>

                <section class="card supplier-inquiry-task hidden" id="supplierInquiryTaskCard" aria-labelledby="supplierInquiryTaskTitle">
                  <div class="card-header supplier-task-header">
                    <div class="supplier-task-heading">
                      <span class="supplier-task-kicker">Assigned inquiry task</span>
                      <h2 id="supplierInquiryTaskTitle">Supplier task</h2>
                      <p id="supplierInquiryTaskDue"></p>
                    </div>
                    <span class="badge badge-gray supplier-task-status" id="supplierInquiryTaskStatus">Not Started</span>
                  </div>
                  <div class="card-body supplier-task-body">
                    <div class="supplier-task-instructions">
                      <strong>What you need to do</strong>
                      <p id="supplierInquiryTaskInstructions"></p>
                    </div>
                    <div class="supplier-task-files">
                      <div class="supplier-task-files-head">
                        <strong>Files sent to FlowTracker</strong>
                        <span id="supplierInquiryTaskFileCount">0 files</span>
                      </div>
                      <ul id="supplierInquiryTaskFiles" class="supplier-task-file-list" aria-label="Submitted task files"></ul>
                      <p id="supplierInquiryTaskFileHint" class="supplier-task-file-hint"></p>
                    </div>
                    <div class="supplier-task-completed hidden" id="supplierInquiryTaskCompleted">
                      <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="m5 12 4 4L19 6"/></svg>
                      <span id="supplierInquiryTaskCompletionText">Task completed in FlowTracker. No further action is needed.</span>
                    </div>
                    <div class="supplier-task-entry" id="supplierInquiryTaskActions">
                      <div class="compact-field supplier-task-file-picker" id="supplierInquiryTaskUploadWrap">
                        <label for="supplierInquiryTaskFile">Costing / supporting file</label>
                        <input id="supplierInquiryTaskFile" type="file" accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.webp,.gif,.zip">
                        <small>Choose one file at a time · PDF, Office, images or ZIP · Maximum 20 MB</small>
                      </div>
                      <div class="supplier-task-actions">
                        <button type="button" class="btn btn-outline-teal btn-sm" id="supplierInquiryTaskUpload" disabled>Upload to FlowTracker</button>
                        <button type="button" class="btn btn-primary btn-sm" id="supplierInquiryTaskComplete">Complete task</button>
                      </div>
                    </div>
                    <p id="supplierInquiryTaskFeedback" class="supplier-task-feedback hidden" role="status" aria-live="polite"></p>
                  </div>
                </section>

                <div class="card">
                  <div class="card-header"><div><h2>Buyer documents</h2><p>Inquiry and task reference files shared by the buyer</p></div><span id="rfqBuyerDocumentCount" class="badge badge-gray">0 files</span></div>
                  <div class="card-body" style="padding-top:0;padding-bottom:0">
                    <div class="doc-list" id="rfqBuyerDocuments"><div class="empty-state"><p>No reference documents have been shared yet.</p></div></div>
                  </div>
                </div>
              </div>

              <aside class="card summary-card">
                <div class="card-header"><div><h2>Quotation summary</h2><p id="summaryStatusText">No quotation submitted yet</p></div></div>
                <div class="summary-body">
                  <div class="summary-product"><div class="product-thumb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M5 9c4-2 10-2 14 0v7c-4 2-10 2-14 0V9Z"/><path d="M5 11 2 9M19 11l3-2M8 11h8M8 14h8"/></svg></div><div><strong id="summaryProductName">Select a requested product</strong><div class="muted" style="font-size:10.5px;margin-top:2px">--</div></div></div>
                  <div class="summary-lines">
                    <div class="summary-line"><span>Supplier</span><strong>{{ $company->name }}</strong></div>
                    <div class="summary-line"><span>Requested quantity</span><strong id="summaryQty">--</strong></div>
                    <div class="summary-line"><span>Quotation due</span><strong id="summaryDue">{{ $quotations->first()['due'] ?? 'No deadline' }}</strong></div>
                  </div>
                  <div class="summary-actions">
                    <button class="btn btn-primary btn-block" id="startQuoteBtn">Start quotation <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg></button>
                    <button class="btn btn-outline-teal btn-block" id="declineBtn">Decline to quote</button>
                  </div>
                  <div class="help-box"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg><span>Your quotation is available only within this supplier company account.</span></div>
                </div>
              </aside>
            </div>
          </section>

          <!-- Quote Workspace -->
          <section id="view-quote-workspace" class="app-view hidden">
            <div class="quote-shell">
              <div class="quote-topline">
                <button class="back-link" id="backToRfq"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m15 18-6-6 6-6"/></svg>Quotation request</button>
              </div>

              <div class="quote-titlebar">
                <div><h1>Submit your quotation</h1><p>Provide your commercial offer for the requested products.</p></div>
                <div class="quote-draft-state"><span class="badge badge-gray" id="quoteDraftBadge">Draft</span><div class="save-state"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg><span id="savedText">Saved a moment ago</span></div></div>
              </div>

              <div class="quote-secure-banner"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>This quotation is private and can only be accessed through your secure supplier account.</div>

              <nav class="quote-progress" id="quoteProgress" aria-label="Quotation stages">
                <button class="quote-progress-step active" data-quote-step="1"><span class="quote-progress-dot">1</span><span>Product details &amp; files</span></button>
                <button class="quote-progress-step" data-quote-step="2"><span class="quote-progress-dot">2</span><span>Pricing &amp; terms</span></button>
                <button class="quote-progress-step" data-quote-step="3"><span class="quote-progress-dot">3</span><span>Review &amp; submit</span></button>
              </nav>

              <div class="quote-layout">
                <div class="quote-main-column">
                  {{-- Independent quotation stages share one in-memory form state; no route reload between steps. --}}
                  @include('portal.quotation.steps.product')
                  @include('portal.quotation.steps.pricing')
                  @include('portal.quotation.steps.review')
                </div>

                <!-- STICKY SUMMARY -->
                <aside class="quote-sidebar">
                  <h3>Quotation summary</h3>
                  <div class="quote-sidebar-product"><div class="product-thumb"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.4"><path d="M5 9c4-2 10-2 14 0v7c-4 2-10 2-14 0V9Z"/><path d="M5 11 2 9M19 11l3-2M8 11h8M8 14h8"/></svg></div><strong>Select a requested product</strong></div>
                  <div class="quote-sidebar-lines"><div class="quote-sidebar-line"><span>Supplier</span><strong>{{ $company->name }}</strong></div><div class="quote-sidebar-line"><span>Requested quantity</span><strong>--</strong></div><div class="quote-sidebar-line"><span>Quotation due</span><strong>{{ $quotations->first()['due'] ?? 'No deadline' }}</strong></div></div>
                  <div class="quote-sidebar-lines"><div class="quote-sidebar-line"><span>Product subtotal</span><strong id="sideSubtotal">USD 0.00</strong></div><div class="quote-sidebar-line"><span>Sample cost</span><strong id="sideSample">USD 0.00</strong></div><div class="quote-sidebar-line"><span>Other costs</span><strong id="sideOther">USD 0.00</strong></div></div>
                  <div class="quote-sidebar-total"><span>Total quoted value</span><strong id="sideTotal">USD 0.00</strong></div>
                  <div class="summary-checks" id="quoteSummaryChecks"></div>
                  <div class="quote-sidebar-actions"><button class="btn btn-outline-teal" type="button" data-save-quote>▣ Save draft</button><button class="btn btn-primary" type="button" id="quoteSidePrimary">Continue to pricing <span style="margin-left:auto">›</span></button><button class="quote-decline-link" type="button" id="quoteDeclineBtn">Decline to quote</button><div class="quote-secure-submission" id="secureSubmissionLabel" style="display:none"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>Secure submission</div></div>
                </aside>
              </div>

              <footer class="quote-page-footer"><div></div><div class="powered">Powered by <strong>FlowTrack</strong></div><div class="footer-links"><a href="#">Privacy</a><a href="#">Terms</a><a href="#">Contact buyer</a></div></footer>
            </div>
          </section>

          <!-- Full supplier module views -->
          <section id="view-orders" @class(['app-view', 'hidden' => $initialView !== 'orders'])>
            <div class="page-header"><div><h1>Orders & Requests</h1><p>Orders created in FlowTrack and assigned to your supplier company.</p></div></div>
            <div class="module-summary-grid">
              <div class="module-summary-card"><small>ASSIGNED ORDERS</small><strong>{{ $orderPage->total() }}</strong><span>Shared securely with your company</span></div>
              <div class="module-summary-card"><small>ORDERS ON THIS PAGE</small><strong>{{ $orders->count() }}</strong><span>Read-only order information</span></div>
              <div class="module-summary-card"><small>ORDER LINES ON THIS PAGE</small><strong>{{ $orders->sum(fn ($order) => count($order['items'])) }}</strong><span>Products included in received orders</span></div>
              <div class="module-summary-card"><small>LATEST UPDATE</small><strong>{{ $orders->first()['created'] ?? '-' }}</strong><span>Most recently received order</span></div>
            </div>
            <div class="table-card"><div class="table-scroll"><table class="compact-table"><thead><tr><th>Order / Product</th><th>Order ref</th><th>Your responsibility</th><th>Required by</th><th>Status</th><th style="text-align:right">Action</th></tr></thead><tbody>
              @forelse($orders as $order)
                <tr><td><div class="primary">{{ $order['title'] }}</div><div class="secondary">{{ collect($order['items'])->pluck('product_name')->take(2)->join(', ') }}</div></td><td>{{ $order['reference'] }}</td><td>Assigned products: {{ count($order['items']) }}</td><td>{{ $order['due'] }}</td><td><span class="badge badge-blue">{{ $order['orderStatus'] }}</span></td><td style="text-align:right"><div style="display:flex;gap:7px;justify-content:flex-end;flex-wrap:wrap">
                    @if($order['approvedArtworkId'] > 0)<a class="btn btn-outline-teal btn-sm" href="{{ route('supplier.orders.artwork', [$order['id'], $order['approvedArtworkId']]) }}">Download artwork</a>@endif
                    <a class="btn btn-outline-teal btn-sm" href="{{ route('supplier.orders.show', $order['id']) }}">View details</a>
                </div></td></tr>
              @empty
                <tr><td colspan="6"><div class="empty-state"><h3>No assigned orders yet</h3><p>Orders will appear here when FlowTrack shares them with your supplier account.</p></div></td></tr>
              @endforelse
            </tbody></table></div></div>
            @if($orderPage->hasPages())
              <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-top:16px">
                <span>Page {{ $orderPage->currentPage() }} of {{ $orderPage->lastPage() }} · {{ $orderPage->total() }} orders</span>
                <div style="display:flex;gap:8px">
                  @if($orderPage->onFirstPage())<span class="btn btn-outline-teal btn-sm" aria-disabled="true">Previous</span>@else<a class="btn btn-outline-teal btn-sm" href="{{ $orderPage->previousPageUrl() }}">Previous</a>@endif
                  @if($orderPage->hasMorePages())<a class="btn btn-outline-teal btn-sm" href="{{ $orderPage->nextPageUrl() }}">Next</a>@else<span class="btn btn-outline-teal btn-sm" aria-disabled="true">Next</span>@endif
                </div>
              </div>
            @endif
          </section>

          <section id="view-company-documents" class="app-view hidden">
            <div class="page-header"><div><h1>Company Documents</h1><p>Maintain registration, tax, banking, agreement, certification and compliance documents for your supplier company.</p></div><div class="header-actions"><button class="btn btn-primary btn-sm" id="uploadCompanyDocBtn">Upload document</button></div></div>
            <div class="attention-banner"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 17h.01"/></svg><div><strong>No compliance alerts received.</strong> Company documents will appear when connected.</div></div>
            <div class="compliance-grid" id="companyDocumentCards"><div class="compliance-card"><strong>No company documents uploaded</strong><small>Upload a registration, tax or compliance document securely.</small></div></div>
          </section>

          <section id="view-notifications" @class(['app-view', 'hidden' => $initialView !== 'notifications'])>
            <div class="page-header supplier-notification-page-heading">
              <div><h1>Notifications</h1><p>Track order and quotation updates in one place.</p></div>
              <div class="header-actions"><button class="btn btn-outline-teal btn-sm" type="button" data-mark-notifications-read @disabled(!$dashboard['unreadNotifications'])>Mark all as read</button></div>
            </div>
            <div id="notificationCenterList" class="supplier-notification-center" data-endpoint="{{ route('supplier.dashboard.notifications.center') }}" data-loaded="{{ $notificationPage ? 'true' : 'false' }}" aria-live="polite">
              @if($notificationPage)
                @include('portal.partials.notification-center')
              @else
                <div class="card"><div class="empty-state"><p>Open notifications to load your latest updates.</p></div></div>
              @endif
            </div>
          </section>

          <section id="view-profile" @class(['app-view', 'hidden' => $initialView !== 'profile'])>
            <div class="page-header"><div><h1>Company Profile</h1><p>Maintain supplier-owned company information used across RFQs, orders, documents and billing.</p></div><div class="header-actions"><span class="badge badge-green">Active supplier</span><button class="btn btn-primary btn-sm" id="saveProfileBtn">Save changes</button></div></div>

            <div class="profile-section-stack">
              <section class="card profile-section-card">
                <div class="card-header profile-section-head"><div><span class="profile-section-kicker">01</span><h2>Company information</h2><p>Core business information used across your supplier account.</p></div></div>
                <div class="card-body">
                  <div class="form-grid cols-3">
                    <div class="compact-field"><label>Company name</label><input value="{{ $company->name }}"></div>
                    <div class="compact-field"><label>Supplier reference</label><input value="{{ $company->reference }}" readonly></div>
                    <div class="compact-field"><label>Supplier status</label><input value="{{ $company->active ? 'Active' : 'Inactive' }}" readonly></div>
                    <div class="compact-field"><label>Legal company name</label><input value=""></div>
                    <div class="compact-field"><label>Country / region</label><input value=""></div>
                    <div class="compact-field"><label>Tax / registration number</label><input value=""></div>
                    <div class="compact-field full"><label>Company description</label><textarea></textarea></div>
                  </div>
                </div>
              </section>

              <section class="card profile-section-card">
                <div class="card-header profile-section-head"><div><span class="profile-section-kicker">02</span><h2>Contacts</h2><p>People FlowTrack can contact for sales, finance and operations.</p></div></div>
                <div class="card-body">
                  <div class="form-grid cols-3">
                    <div class="compact-field"><label>Primary contact</label><input value=""></div>
                    <div class="compact-field"><label>Primary email</label><input value=""></div>
                    <div class="compact-field"><label>Phone</label><input value=""></div>
                    <div class="compact-field"><label>Finance contact</label><input value=""></div>
                    <div class="compact-field"><label>Finance email</label><input value=""></div>
                    <div class="compact-field"><label>Operations contact</label><input value=""></div>
                  </div>
                </div>
              </section>

              <section class="card profile-section-card">
                <div class="card-header profile-section-head"><div><span class="profile-section-kicker">03</span><h2>Addresses</h2><p>Registered and production locations associated with your company.</p></div></div>
                <div class="card-body">
                  <div class="form-grid cols-2">
                    <div class="compact-field full"><label>Registered address</label><textarea></textarea></div>
                    <div class="compact-field full"><label>Factory / production address</label><textarea></textarea></div>
                    <div class="compact-field"><label>City</label><input value=""></div>
                    <div class="compact-field"><label>Postal code</label><input value=""></div>
                  </div>
                </div>
              </section>

              <section class="card profile-section-card">
                <div class="card-header profile-section-head"><div><span class="profile-section-kicker">04</span><h2>Payment details</h2><p>Banking and payment information used for supplier settlement.</p></div></div>
                <div class="card-body">
                  <div class="info-banner"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.6 2.8 8.5 7 10 4.2-1.5 7-5.4 7-10V6l-7-3Z"/></svg><div>Payment details are sensitive supplier information and should be available only to authorized internal finance users.</div></div>
                  <div class="form-grid cols-3">
                    <div class="compact-field"><label>Beneficiary name</label><input value=""></div>
                    <div class="compact-field"><label>Bank name</label><input value=""></div>
                    <div class="compact-field"><label>Currency</label><select><option>USD</option><option>CNY</option><option>GBP</option><option>BDT</option><option>EUR</option><option>GBP</option></select></div>
                    <div class="compact-field"><label>Account ending</label><input value="Not provided" readonly></div>
                    <div class="compact-field"><label>SWIFT / BIC</label><input value=""></div>
                    <div class="compact-field"><label>Payment terms</label><input value=""></div>
                  </div>
                </div>
              </section>

              <section class="card profile-section-card">
                <div class="card-header profile-section-head"><div><span class="profile-section-kicker">05</span><h2>Products / categories</h2><p>Products and categories currently associated with your supplier profile.</p></div></div>
                <div class="card-body">
                  <div style="padding:12px 0"><a class="btn btn-primary btn-sm" href="{{ route('supplier.products.prices') }}">View assigned products &amp; request price changes</a></div>
                  <div class="table-scroll"><table class="compact-table"><thead><tr><th>Product / Category</th><th>Relationship</th><th>Capability</th><th>Status</th></tr></thead><tbody><tr><td colspan="4">Approved categories will appear here once assigned.</td></tr></tbody></table></div>
                </div>
              </section>
            </div>
          </section>

          <section id="view-help" @class(['app-view', 'hidden' => $initialView !== 'help'])>
            <div class="page-header"><div><h1>Help & support</h1><p>Get assistance with RFQs, orders, documents, bills, account access or supplier profile information.</p></div></div>
            <div class="module-grid"><div class="card"><div class="card-header"><div><h2>Contact FlowTrack buyer / operations</h2><p>Questions remain attached to the related transaction</p></div></div><div class="card-body"><div class="form-grid cols-2"><div class="compact-field"><label>Related to</label><select><option>RFQ / quotation</option><option>Order / request</option><option>Document request</option><option>Bill / payment</option><option>Company profile</option></select></div><div class="compact-field"><label>Reference</label><input placeholder="RFQ, order or invoice reference"></div><div class="compact-field full"><label>Message</label><textarea placeholder="Describe what you need help with..."></textarea></div></div><button class="btn btn-primary btn-sm" style="margin-top:12px" data-portal-support>Send message</button></div></div><aside class="card"><div class="card-header"><div><h2>Support topics</h2></div></div><div class="card-body"><div class="status-line"><div><strong>RFQ deadlines</strong><span>Quotation and revision help</span></div></div><div class="status-line"><div><strong>Documents & uploads</strong><span>Submission and document guidance</span></div></div><div class="status-line"><div><strong>Bills & payments</strong><span>Invoice status and payment reference</span></div></div><div class="status-line"><div><strong>Account access</strong><span>Password and supplier users</span></div></div></div></aside></div>
          </section>
        </div>
      </main>
    </div>
  </section>


  <div id="notificationPanel" class="drawer-backdrop hidden">
    <aside class="notification-drawer" role="dialog" aria-modal="true" aria-labelledby="notificationDrawerTitle" tabindex="-1">
      <header class="drawer-head">
        <div class="drawer-heading">
          <h2 id="notificationDrawerTitle">Notifications</h2>
          <p><span id="notificationDrawerCount" aria-live="polite">{{ $dashboard['unreadNotifications'] }} unread</span> · Latest RFQ and order activity</p>
        </div>
        <button class="icon-button drawer-close" type="button" id="closeNotifications" aria-label="Close notifications"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18"/></svg></button>
      </header>
      <div class="drawer-controls">
        <div class="drawer-filter-tabs" role="group" aria-label="Notification status">
          <button class="drawer-filter is-active" type="button" data-notification-filter="all" aria-pressed="true">All</button>
          <button class="drawer-filter" type="button" data-notification-filter="unread" aria-pressed="false">Unread</button>
        </div>
        <label class="drawer-type-filter" for="notificationDrawerTypeFilter">
          <span class="sr-only">Notification type</span>
          <select id="notificationDrawerTypeFilter" aria-label="Notification type">
            <option value="all">All types</option>
            <option value="rfq">RFQs</option>
            <option value="order">Orders</option>
          </select>
        </label>
      </div>
      <div class="drawer-list-head">
        <span id="notificationDrawerShowing" aria-live="polite">Recent activity</span>
        <button class="drawer-mark-read" type="button" data-mark-notifications-read @disabled(!$dashboard['unreadNotifications'])>Mark all as read</button>
      </div>
      <div class="drawer-body">
        <div id="notificationDrawerList">@include('portal.partials.notifications', $dashboard)</div>
        <div id="notificationDrawerEmptyFilter" class="notice-empty-filter hidden" role="status">
          <span class="notice-empty-symbol" aria-hidden="true">✓</span>
          <strong>No matching updates</strong>
          <p>Try another filter, or see all notifications.</p>
        </div>
      </div>
      <footer class="drawer-foot">
        <button class="btn btn-outline-teal drawer-view-all" type="button" id="notificationQuotesBtn">View all notifications <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M5 12h14m-6-6 6 6-6 6"/></svg></button>
      </footer>
    </aside>
  </div>

  <div id="quoteSuccessModal" class="modal-backdrop hidden">
    <div class="modal" style="width:min(430px,100%)">
      <div class="modal-body" style="padding:25px 22px 18px">
        <div class="submit-success-icon"><svg class="icon" style="width:28px;height:28px" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg></div>
        <div class="submit-success-copy"><h3>Quotation submitted</h3><p>Your quotation and documents are sent securely to FlowTracker.</p></div>
      </div>
      <div class="modal-foot" style="justify-content:center"><button class="btn btn-primary" id="successBackToQuotes">Back to quotations</button></div>
    </div>
  </div>



  <div id="accountModal" class="modal-backdrop hidden">
    <div class="modal" style="width:min(360px,100%)">
      <div class="modal-head"><strong>Supplier account</strong><button class="icon-button" data-close-account><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
      <div class="modal-body"><div style="display:flex;gap:10px;align-items:center;margin-bottom:13px"><div class="avatar" style="width:42px;height:42px">{{ mb_strtoupper(mb_substr($user->name, 0, 2)) }}</div><div><strong style="color:#263a57">{{ $user->name }}</strong><div class="muted" style="font-size:11px">{{ $user->email }}</div><div class="muted" style="font-size:11px">{{ $company->name }}</div></div></div><div class="security-note" style="margin:0"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M12 3 5 6v5c0 4.6 2.8 8.5 7 10 4.2-1.5 7-5.4 7-10V6l-7-3Z"/></svg><div>Session belongs only to this supplier identity and company scope.</div></div></div>
      <div class="modal-foot" style="justify-content:space-between"><button class="btn btn-ghost">Security settings</button><form method="POST" action="{{ route('supplier.logout') }}">@csrf<button type="submit" class="btn btn-danger-ghost" id="logoutBtn">Sign out</button></form></div>
    </div>
  </div>

  <div id="declineModal" class="modal-backdrop hidden">
    <div class="modal">
      <div class="modal-head"><strong>Decline quotation request</strong><button class="icon-button" data-close-decline><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 6 12 12M18 6 6 18"/></svg></button></div>
      <div class="modal-body"><p>Tell the buyer why your company will not quote this request. The buyer will be notified and the reason will be saved with this quotation request.</p><div class="compact-field"><label>Reason</label><select><option>Unable to meet requested delivery date</option><option>Product outside our capability</option><option>Quantity not suitable</option><option>Other</option></select></div><div class="compact-field" style="margin-top:10px"><label>Optional note</label><textarea placeholder="Add a brief note for the buyer"></textarea></div></div>
      <div class="modal-foot"><button class="btn" data-close-decline>Cancel</button><button class="btn" style="border-color:#dc9b9b;color:#aa3838" id="confirmDeclineBtn">Decline request</button></div>
    </div>
  </div>

  <div id="companyDocModal" class="modal-backdrop hidden"><div class="modal" role="dialog" aria-modal="true" aria-label="Upload company document"><div class="modal-head"><strong>Upload company document</strong><button type="button" class="icon-button" data-close-company-doc aria-label="Close">&times;</button></div><div class="modal-body"><p>Documents stay private to your supplier company.</p><div class="compact-field"><label for="companyDocType">Document category</label><select id="companyDocType"><option>Business registration</option><option>Tax certificate</option><option>Factory audit report</option><option>Bank information</option><option>Quality certification</option><option>Supplier agreement</option><option>Other</option></select></div><div class="compact-field" style="margin-top:12px"><label for="companyDocExpiry">Expiry date (optional)</label><input id="companyDocExpiry" type="date"></div><div class="compact-field" style="margin-top:12px"><label for="companyDocFile">File (PDF, XLSX, DOCX, JPG, PNG; max 20 MB)</label><input id="companyDocFile" type="file" accept=".pdf,.xlsx,.docx,.jpg,.jpeg,.png"></div></div><div class="modal-foot"><button class="btn" type="button" data-close-company-doc>Cancel</button><button class="btn btn-primary" type="button" id="confirmCompanyDoc">Upload securely</button></div></div></div>
<div id="toast" class="toast hidden"><span class="ok"><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg></span><span id="toastText">Saved</span></div>

  <script nonce="{{ request()->attributes->get('csp_nonce') }}">window.supplierPortalConfig = {realtime: {{ Illuminate\Support\Js::from($realtimeConfig) }}, dashboardRevision: {{ Illuminate\Support\Js::from((string) $company->dashboard_revision) }}, rfqActionCount: {{ $dashboard['rfqActionCount'] }}, quotations: {{ Illuminate\Support\Js::from($quotations) }}, companyName: {{ Illuminate\Support\Js::from($company->name) }}, profile: {{ Illuminate\Support\Js::from($profile) }}, banking: {{ Illuminate\Support\Js::from($maskedBank) }}, role: {{ Illuminate\Support\Js::from($user->role) }}};</script><script src="{{ asset('assets/supplier-notifications.js') }}?v={{ filemtime(public_path('assets/supplier-notifications.js')) }}" defer></script><script src="{{ asset('assets/portal.js') }}?v={{ filemtime(public_path('assets/portal.js')) }}" defer></script><script src="{{ asset('assets/supplier-dashboard-realtime.js') }}?v={{ filemtime(public_path('assets/supplier-dashboard-realtime.js')) }}" defer></script><script src="{{ asset('assets/supplier-mobile.js') }}?v={{ filemtime(public_path('assets/supplier-mobile.js')) }}" defer nonce="{{ request()->attributes->get('csp_nonce') }}"></script>
<script src="{{ asset('assets/supplier-session.js') }}?v={{ filemtime(public_path('assets/supplier-session.js')) }}" data-activity-url="{{ route('supplier.session.activity') }}" data-logout-url="{{ route('supplier.logout') }}" data-login-url="{{ route('supplier.login') }}" defer></script>
</body></html>