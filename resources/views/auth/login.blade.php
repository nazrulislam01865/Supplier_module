<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="csrf-token" content="{{ csrf_token() }}">@include('portal.partials.flowtrack-favicon')
<title>{{ $title ?? 'FlowTrack Supplier Portal' }}</title><link rel="stylesheet" href="{{ asset('assets/supplier.css') }}?v={{ filemtime(public_path('assets/supplier.css')) }}"><link rel="stylesheet" href="{{ asset('assets/supplier-mobile.css') }}?v={{ filemtime(public_path('assets/supplier-mobile.css')) }}"></head><body><section id="loginView" class="login-screen">
    <div class="login-panel">
      <div class="login-top">
        <div class="brand login-brand">
          @if($brandLogo)
            <img class="login-brand-logo" src="{{ $brandLogo }}" alt="{{ $brandName }}" decoding="async">
          @else
            <span class="brand-mark"><span class="brand-dot"></span></span><span>{{ $brandName }}</span>
          @endif
        </div>
        <div class="secure-label">
          <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
          Supplier Portal
        </div>
      </div>

      <div class="login-center">
        <div class="login-card">
          <div class="access-pill">
            <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 3 5 6v5c0 4.6 2.8 8.5 7 10 4.2-1.5 7-5.4 7-10V6l-7-3Z"/><path d="m9 12 2 2 4-4"/></svg>
            Invitation-only supplier access
          </div>
          <h1>Welcome back</h1>

          <form id="loginForm" method="POST" action="{{ route('supplier.login.post') }}">
            @csrf
            @if($errors->any()) <div class="attention-banner" role="alert">{{ $errors->first() }}</div> @endif
            @if(session('supplier_session_expired') || request()->query('session_expired') === '1')
              <div class="login-session-expired" role="alert">For security, sessions expire after 30 minutes of inactivity.</div>
            @endif
            @if(session('status')) <div class="info-banner" role="status">{{ session('status') }}</div> @endif
            <div class="field">
              <label for="email">Username or email address</label>
              <div class="input-wrap">
                <svg class="icon-sm leading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/></svg>
                <input id="email" type="text" name="email" value="{{ old('email') }}" autocomplete="username" required />
              </div>
            </div>
            <div class="field">
              <label for="password">Password</label>
              <div class="input-wrap">
                <svg class="icon-sm leading" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><rect x="5" y="10" width="14" height="10" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                <input id="password" type="password" name="password" autocomplete="current-password" required />
                <button type="button" class="password-toggle" id="togglePassword" aria-label="Show password">
                  <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                </button>
              </div>
            </div>
            <div class="field-row">
              <a href="{{ route('password.request') }}" class="btn btn-ghost btn-sm link">Forgot password?</a>
            </div>
            <button type="submit" class="btn btn-primary btn-lg btn-block">
              Sign in to supplier portal
              <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m9 18 6-6-6-6"/></svg>
            </button>
          </form>

        </div>
      </div>

      <div class="login-footer">
        @if($footer['copyright'] !== '')<span>{{ $footer['copyright'] }}</span>@endif
        @foreach($footer['links'] as $item)
          @if($item['enabled'] && $item['label'] !== '')
            @if($item['url'] !== '')<a href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer">{{ $item['label'] }}</a>
            @else<span>{{ $item['label'] }}</span>@endif
          @endif
        @endforeach
      </div>
    </div>

    <div class="login-art">
      <div class="hero-shell">
        <div class="kicker">
          <svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 6h16M4 12h10M4 18h7"/></svg>
          Built for faster supplier responses
        </div>
        <h2>Everything you need to quote, in one place.</h2>
        <p>See new quotation requests, understand what is due next, upload supporting documents and keep every response organized from one supplier workspace.</p>

        <div class="hero-deadline">
          <div><small>NEXT QUOTATION DEADLINE</small><strong>1 Layer Face Mask · RFQ-PRD-034955-JQ</strong></div>
          <div class="countdown">2 days left</div>
        </div>

        <div class="preview-window">
          <div class="preview-top"><span class="dot"></span><span class="dot"></span><span class="dot"></span></div>
          <div class="preview-body">
            <div class="preview-header">
              <div><strong>Your quotation workspace</strong><span>Clear priorities, simple actions</span></div>
              <span class="mini-status">All systems ready</span>
            </div>
            <div class="mini-cards">
              <div class="mini-card"><small>Needs attention</small><strong>3</strong></div>
              <div class="mini-card"><small>In draft</small><strong>2</strong></div>
              <div class="mini-card"><small>Submitted</small><strong>18</strong></div>
            </div>
            <div class="mini-row">
              <div class="mini-product"><span class="mini-thumb"></span><div><strong>1 Layer Face Mask</strong><br><span>RFQ-PRD-034955-JQ</span></div></div>
              <div>1,000 units</div>
              <div>Sep 5</div>
              <div class="mini-status">Review</div>
            </div>
          </div>
        </div>

        <div class="hero-points">
          <span><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg>RFQs in one place</span>
          <span><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg>Automatic draft saving</span>
          <span><svg class="icon-sm" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m5 12 4 4L19 6"/></svg>Protected document access</span>
        </div>
      </div>
    </div>
  </section><script nonce="{{ request()->attributes->get('csp_nonce') }}">
document.getElementById('togglePassword').addEventListener('click',function(){const input=document.getElementById('password');input.type=input.type==='password'?'text':'password';});
// The timeout URL is a one-time display marker, not a permanent login state.
const loginLocation = new URL(window.location.href);
if (loginLocation.searchParams.has('session_expired')) {
  loginLocation.searchParams.delete('session_expired');
  window.history.replaceState(null, '', loginLocation.pathname + loginLocation.search + loginLocation.hash);
}
</script></body></html>