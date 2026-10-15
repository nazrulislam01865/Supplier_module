<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">@include('portal.partials.flowtrack-favicon')
<title>Change password · FlowTrack Supplier Portal</title><link rel="stylesheet" href="{{ asset('assets/supplier.css') }}"><link rel="stylesheet" href="{{ asset('assets/supplier-mobile.css') }}?v={{ filemtime(public_path('assets/supplier-mobile.css')) }}"></head>
<body><div id="loginView"><div class="login-panel" style="grid-column:1/-1"><div class="login-top"><div class="brand"><span class="brand-mark"><span class="brand-dot"></span></span>FlowTrack</div><div class="secure-label">Supplier Portal</div></div>
<div class="login-center"><div class="login-card"><h1>Set your own password</h1><p>An administrator has given you a temporary password. Change it before accessing your supplier workspace.</p>
@if($errors->any())<div class="attention-banner" role="alert">{{ $errors->first() }}</div>@endif
<form method="POST" action="{{ route('supplier.password.update') }}">@csrf
<div class="field"><label for="current_password">Temporary password</label><input id="current_password" type="password" name="current_password" required autocomplete="current-password"></div>
<div class="field"><label for="password">New password</label><input id="password" type="password" name="password" required autocomplete="new-password" minlength="8"></div>
<div class="field"><label for="password_confirmation">Confirm new password</label><input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" minlength="8"></div>
<p>Use at least 8 characters.</p><button type="submit" class="btn btn-primary btn-lg btn-block">Save password and continue</button>
</form></div></div></div></div><script src="{{ asset('assets/supplier-session.js') }}?v={{ filemtime(public_path('assets/supplier-session.js')) }}" data-activity-url="{{ route('supplier.session.activity') }}" data-logout-url="{{ route('supplier.logout') }}" data-login-url="{{ route('supplier.login') }}" defer></script>
</body></html>
