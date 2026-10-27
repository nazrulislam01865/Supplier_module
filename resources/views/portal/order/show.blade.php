<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('portal.partials.flowtrack-favicon')
<title>{{ $summary['title'] }} · FlowTrack Supplier Portal</title>
    <link rel="stylesheet" href="{{ asset('assets/supplier.css') }}?v={{ filemtime(public_path('assets/supplier.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/supplier-workflow.css') }}?v={{ filemtime(public_path('assets/supplier-workflow.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/supplier-mobile.css') }}?v={{ filemtime(public_path('assets/supplier-mobile.css')) }}">
</head>
<body id="supplierOrderView" class="supplier-prototype-body"
    data-order-realtime-id="{{ $order->id }}"
    data-order-realtime-revision="{{ (string) ($order->payload['revision'] ?? '0') }}"
    data-order-realtime-supplier="{{ (int) $company->flowtrack_supplier_id }}"
    data-order-realtime-source="{{ (int) ($order->payload['source_id'] ?? 0) }}"
    data-order-realtime-key="{{ (string) config('supplier_realtime.key') }}"
    data-order-realtime-host="{{ (string) config('supplier_realtime.host') }}"
    data-order-realtime-port="{{ (int) config('supplier_realtime.port') }}"
    data-order-realtime-scheme="{{ (string) config('supplier_realtime.scheme') }}"
    data-order-realtime-auth="{{ route('supplier.orders.realtime.auth', $order->id) }}"
    data-order-realtime-revision-url="{{ route('supplier.orders.realtime.revision', $order->id) }}">
<section id="portalView">
    <header class="topbar">
        <div class="topbar-left">
            <button class="btn btn-ghost btn-sm mobile-menu" id="mobileMenuBtn" aria-label="Open menu" type="button">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
            </button>
            <a class="brand" href="{{ route('supplier.home') }}">@include('portal.partials.flowtrack-brand')</a>
        </div>
        <div class="topbar-right">
            <span class="sync-state"><span class="sync-dot"></span>Secure supplier session</span>
            <a class="notification-btn" href="{{ route('supplier.home') }}?view=notifications" aria-label="Notifications">
                <svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/></svg>
                @if($hasUnreadNotifications)<span class="notification-dot"></span>@endif
            </a>
            <div class="account">
                <div class="avatar">{{ strtoupper(substr($user->name, 0, 1)) }}</div>
                <div class="account-meta"><strong>{{ $user->name }}</strong><small>{{ $company->name }}</small></div>
                <a class="icon-button" href="{{ route('supplier.home') }}?view=profile" aria-label="Company profile">
                    <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="m6 9 6 6 6-6"/></svg>
                </a>
            </div>
        </div>
    </header>
    <div class="shell">
        @include('portal.partials.sidebar', ['isOrderDetail' => true])
        <main class="main supplier-prototype-main">
        <div class="supplier-prototype-content">
            <div class="supplier-prototype-breadcrumb-row">
                <div class="supplier-prototype-breadcrumbs">
                    <a href="{{ rtrim(route('supplier.home'), '/').'/' }}?view=orders">Orders</a>
                    <span>/</span>
                    <strong>{{ $summary['breadcrumb_reference'] }}</strong>
                    <button type="button" class="supplier-prototype-copy-btn" aria-label="Copy order reference" data-copy-reference="{{ $summary['breadcrumb_reference'] }}">
                        @include('portal.order.partials.svg.copy')
                    </button>
                </div>
                <a href="{{ rtrim(route('supplier.home'), '/').'/' }}?view=orders" class="supplier-prototype-back-btn">
                    @include('portal.order.partials.svg.arrow-left')
                    <span>Back to orders</span>
                </a>
            </div>

            <header class="supplier-prototype-header">
                <h1>{{ $summary['title'] }}</h1>
            </header>
            <div class="supplier-prototype-grid">
                <div class="supplier-prototype-primary-column">
                    <section class="supplier-prototype-card supplier-prototype-card--table">
                        <header class="supplier-prototype-card-head"><h2>Products (Your supply)</h2></header>
                        <div class="supplier-prototype-table-wrap">
                            <table class="supplier-prototype-table">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Quantity</th>
                                        <th>Unit Price</th>
                                        <th>Total Price</th>
                                    </tr>
                                </thead>
                                <tbody>
                                @forelse($items as $item)
                                    <tr>
                                        <td>
                                            <div class="supplier-prototype-product-cell">
                                                <div class="supplier-prototype-product-thumb supplier-prototype-file-placeholder" aria-hidden="true">@include('portal.order.partials.svg.box')</div>
                                                <div>
                                                    <strong>{{ $item['product_name'] }}</strong>
                                                    @if(filled($item['notes']))<small class="supplier-order-product-notes">{{ $item['notes'] }}</small>@endif
                                                </div>
                                            </div>
                                        </td>
                                        <td><strong class="supplier-prototype-table-strong">{{ $item['quantity_label'] }}</strong></td>
                                        <td><strong class="supplier-prototype-table-strong">{{ $item['unit_price_label'] }}</strong></td>
                                        <td><strong class="supplier-prototype-table-strong">{{ $item['total_price_label'] }}</strong></td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="supplier-prototype-empty-row">No product lines available.</td>
                                    </tr>
                                @endforelse
                                </tbody>
                            </table>
                        </div>
                    </section>
                    @include('portal.order.partials.workflow', ['workflow' => $workflow, 'order' => $order])
                </div>

                <aside class="supplier-prototype-side-column">
                    <section class="supplier-prototype-card">
                        <header class="supplier-prototype-card-head supplier-prototype-card-head--icon">
                            <div class="supplier-prototype-side-title">
                                <span class="supplier-prototype-side-icon">@include('portal.order.partials.svg.calendar')</span>
                                <h2>Recent updates</h2>
                            </div>
                        </header>
                        <div class="supplier-prototype-card-body supplier-prototype-recent-list">
                            @forelse($recentUpdates as $update)
                                @include('portal.order.partials.recent-update', ['update' => $update])
                            @empty
                                <div class="supplier-prototype-empty-state">No workflow updates have been received yet.</div>
                            @endforelse
                        </div>
                    </section>

                    <section class="supplier-prototype-card">
                        <header class="supplier-prototype-card-head supplier-prototype-card-head--icon">
                            <div class="supplier-prototype-side-title">
                                <span class="supplier-prototype-side-icon">@include('portal.order.partials.svg.file')</span>
                                <h2>Approved artwork</h2>
                            </div>
                        </header>
                        <div class="supplier-prototype-card-body">
                            @if(count($approvedArtworks))
                                @foreach($approvedArtworks as $approvedArtwork)
                                <div class="supplier-prototype-side-artwork-file">
                                    <div class="supplier-prototype-artwork-media">
                                        <div class="supplier-prototype-artwork-thumb supplier-prototype-file-placeholder" aria-hidden="true">@include('portal.order.partials.svg.file-doc')</div>
                                        <div class="supplier-prototype-artwork-copy">
                                            <strong>{{ $approvedArtwork['name'] }}</strong>
                                            <span>{{ number_format(($approvedArtwork['size'] ?? 0) / 1048576, 1) }} MB &nbsp; • &nbsp; {{ strtoupper(pathinfo($approvedArtwork['name'], PATHINFO_EXTENSION) ?: 'FILE') }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="supplier-prototype-key-value-list">
                                    @if(filled($approvedArtwork['uploaded_at'] ?? null))
                                        <div><span>Uploaded on</span><strong>{{ \Illuminate\Support\Carbon::parse($approvedArtwork['uploaded_at'])->format('M j, Y') }}</strong></div>
                                    @endif
                                    @if(!empty($approvedArtwork['version']))
                                        <div><span>Version</span><strong>V{{ $approvedArtwork['version'] }}</strong></div>
                                    @endif
                                </div>
                                <a class="supplier-prototype-view-btn" style="display:inline-flex;margin:9px 0 16px" href="{{ route('supplier.orders.artwork', [$order->id, $approvedArtwork['id']]) }}">Download artwork</a>
                                @endforeach
                            @else
                                <div class="supplier-prototype-empty-state">No approved artwork file available. It will appear after confirmation and synchronization.</div>
                            @endif
                        </div>
                    </section>
                </aside>
            </div>
        </div>
        </main>
    </div>
</section>
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
(function () {
    var menu = document.getElementById('mobileMenuBtn');
    var sidebar = document.getElementById('sidebar');
    if (menu && sidebar) {
        menu.addEventListener('click', function () { sidebar.classList.toggle('open'); });
    }
    document.querySelectorAll('[data-stage-toggle]').forEach(function (button) {
        var panel = document.getElementById(button.getAttribute('aria-controls'));
        if (!panel) return;
        button.addEventListener('click', function () {
            panel.hidden = !panel.hidden;
            button.setAttribute('aria-expanded', String(!panel.hidden));
            button.classList.toggle('is-collapsed', panel.hidden);
        });
    });
    var workflow = document.querySelector('[data-supplier-workflow]');
    if (workflow) {
        workflow.querySelectorAll('[data-flow-stage]').forEach(function (button) {
            button.addEventListener('click', function () {
                if (button.getAttribute('aria-disabled') === 'true') return;
                workflow.querySelectorAll('[data-flow-stage]').forEach(function (card) {
                    var selected = card === button;
                    card.classList.toggle('viewing', selected);
                    card.setAttribute('aria-selected', String(selected));
                });
                workflow.querySelectorAll('[data-flow-panel]').forEach(function (panel) {
                    panel.hidden = panel.dataset.flowPanel !== button.dataset.flowStage;
                });
            });
        });
    }
    // Keep every action dialog outside the task table's grid and overflow container.
    // The native modal stays in the browser top layer, independent of task-row height.
    var actionDialogs = Array.prototype.slice.call(document.querySelectorAll('[data-supplier-action-dialog]'));
    actionDialogs.forEach(function (dialog) {
        document.body.appendChild(dialog);
        var opener = document.querySelector('[data-supplier-modal-open="' + dialog.id + '"]');
        var form = dialog.querySelector('[data-supplier-action-form]');
        if (!form) return;
        function closeDialog() {
            if (dialog.open) dialog.close();
        }
        if (opener) opener.addEventListener('click', function () {
            if (typeof dialog.showModal === 'function' && !dialog.open) dialog.showModal();
        });
        dialog.querySelectorAll('[data-supplier-modal-close]').forEach(function (button) {
            button.addEventListener('click', closeDialog);
        });
        dialog.addEventListener('close', function () {
            if (opener) opener.focus({ preventScroll: true });
        });
        var choice = form.querySelector('[data-supplier-client-choice]');
        var submit = form.querySelector('[data-supplier-action-submit]');
        var stage = function (name) {
            form.querySelectorAll('[data-client-decision-stage]').forEach(function (panel) {
                panel.hidden = panel.dataset.clientDecisionStage !== name;
            });
        };
        if (choice) {
            submit.disabled = !choice.value;
            if (choice.value === 'revise') stage('revision');
            else if (choice.value === 'sample' || choice.value === 'confirm') stage('sample');
            form.querySelectorAll('[data-client-action]').forEach(function (button) {
                button.addEventListener('click', function () {
                    var decision = button.dataset.clientAction;
                    if (decision === 'approved') {
                        choice.value = '';
                        submit.disabled = true;
                        stage('sample');
                    } else if (decision === 'revise') {
                        choice.value = 'revise';
                        submit.disabled = false;
                        stage('revision');
                    } else {
                        choice.value = decision;
                        submit.disabled = false;
                        form.querySelectorAll('[data-client-action]').forEach(function (other) {
                            other.classList.toggle('selected', other === button);
                        });
                    }
                });
            });
            form.querySelectorAll('[data-client-back]').forEach(function (button) {
                button.addEventListener('click', function () {
                    choice.value = '';
                    submit.disabled = true;
                    stage('choice');
                });
            });
        }
        form.addEventListener('submit', function (event) {
            if (choice && !choice.value) {
                event.preventDefault();
                return;
            }
            if (form.dataset.submitting === 'true') { event.preventDefault(); return; }
            if (form.checkValidity()) {
                form.dataset.submitting = 'true';
                submit.disabled = true;
                submit.textContent = 'Saving in FlowTracker…';
            }
        });
    });
    // Reopen the original modal after server-side validation and preserve form fields.
    var invalidTask = @json(old('_supplier_workflow_task'));
    if (invalidTask) {
        var invalidDialog = document.getElementById('supplier-flow-dialog-' + invalidTask);
        if (invalidDialog && typeof invalidDialog.showModal === 'function' && !invalidDialog.open) invalidDialog.showModal();
    }
    var copy = document.querySelector('[data-copy-reference]');
    if (copy && navigator.clipboard && navigator.clipboard.writeText) {
        copy.addEventListener('click', function () {
            navigator.clipboard.writeText(copy.getAttribute('data-copy-reference')).then(function () {
                copy.setAttribute('aria-label', 'Order reference copied');
            }).catch(function () {});
        });
    }
})();
</script>
<script src="{{ asset('assets/supplier-order-realtime.js') }}?v={{ filemtime(public_path('assets/supplier-order-realtime.js')) }}" defer nonce="{{ request()->attributes->get('csp_nonce') }}"></script>
<script src="{{ asset('assets/supplier-mobile.js') }}?v={{ filemtime(public_path('assets/supplier-mobile.js')) }}" defer nonce="{{ request()->attributes->get('csp_nonce') }}"></script>
<script src="{{ asset('assets/supplier-session.js') }}?v={{ filemtime(public_path('assets/supplier-session.js')) }}" data-activity-url="{{ route('supplier.session.activity') }}" data-logout-url="{{ route('supplier.logout') }}" data-login-url="{{ route('supplier.login') }}" defer></script>
</body>
</html>
