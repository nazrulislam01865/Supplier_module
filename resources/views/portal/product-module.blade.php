<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @include('portal.partials.flowtrack-favicon')
    <title>Product · FlowTrack Supplier Portal</title>
    <link rel="stylesheet" href="{{ asset('assets/supplier.css') }}?v={{ filemtime(public_path('assets/supplier.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/supplier-mobile.css') }}?v={{ filemtime(public_path('assets/supplier-mobile.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/supplier-product-module.css') }}?v={{ filemtime(public_path('assets/supplier-product-module.css')) }}">
    <link rel="stylesheet" href="{{ asset('assets/flowtracker-product-parity.css') }}?v={{ filemtime(public_path('assets/flowtracker-product-parity.css')) }}">
    @if($group === 'product_category')
    <link rel="stylesheet" href="{{ asset('assets/flowtracker-product-categories.css') }}?v={{ filemtime(public_path('assets/flowtracker-product-categories.css')) }}">
    @endif
</head>
<body>
<section id="portalView" class="supplier-product-module-page">
    <header class="topbar">
        <div class="topbar-left">
            <button class="btn btn-ghost btn-sm mobile-menu" id="mobileMenuBtn" type="button" aria-label="Open menu"><svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M4 7h16M4 12h16M4 17h16"/></svg></button>
            <a class="brand" href="{{ route('supplier.home') }}?view=dashboard">@include('portal.partials.flowtrack-brand')</a>
        </div>
        <div class="topbar-right"><span class="sync-state"><span class="sync-dot"></span>Secure API connection</span>
            <div class="account"><div class="avatar">{{ mb_strtoupper(mb_substr($user->name,0,1)) }}</div><div class="account-meta"><strong>{{ $user->name }}</strong><small>{{ $company->name }}</small></div></div>
        </div>
    </header>
    <div class="shell">
        @include('portal.partials.sidebar', ['isProductModule' => true])
        <main class="main supplier-product-module-main" id="productNativeMain">
            <div class="ft-native-product ft-master-page">
                @if(session('success'))<div class="ft-native-alert is-success" role="status">{{ session('success') }}</div>@endif
                @if($errors->any())<div class="ft-native-alert is-danger" role="alert">{{ $errors->first() }}</div>@endif
                @if($moduleError)
                    <div class="supplier-product-module-error" role="alert"><h2>Product API connection unavailable</h2><p>{{ $moduleError }}</p><a class="ft-native-btn is-primary" href="{{ route('supplier.products.module', ['group' => $group] + ($create ? ['create'=>1] : [])) }}">Retry connection</a></div>
                @elseif($group === 'product_category')
                    @include('portal.product-native.categories')
                @elseif($create || $edit)
                    @include('portal.product-native.product-form')
                @elseif($id > 0)
                    @include('portal.product-native.product-detail')
                @else
                    @include('portal.product-native.products')
                @endif
            </div>
        </main>
    </div>
</section>
<script src="{{ asset('assets/supplier-product-module.js') }}?v={{ filemtime(public_path('assets/supplier-product-module.js')) }}" defer></script>
<script src="{{ asset('assets/supplier-mobile.js') }}?v={{ filemtime(public_path('assets/supplier-mobile.js')) }}" defer></script>
<script src="{{ asset('assets/supplier-session.js') }}?v={{ filemtime(public_path('assets/supplier-session.js')) }}" data-activity-url="{{ route('supplier.session.activity') }}" data-logout-url="{{ route('supplier.logout') }}" data-login-url="{{ route('supplier.login') }}" defer></script>
</body></html>
