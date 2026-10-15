<?php
namespace App\Http\Controllers;

use App\Models\SupplierCompany;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function loginForm()
    {
        // Guest visitors have no supplier context yet. Show the most recently
        // synchronized FlowTracker site branding, never a supplier's identity.
        $branding = SupplierCompany::query()
            ->where('active', true)
            ->whereNotNull('flowtrack_brand_name')
            ->orderByDesc('brand_revision')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first(['flowtrack_brand_name', 'flowtrack_brand_logo_url', 'flowtrack_brand_favicon_url', 'flowtrack_portal_footer']);

        return view('auth.login', [
            'brandName' => $branding?->flowtrack_brand_name ?: 'FlowTrack',
            'brandLogo' => $branding?->flowtrack_brand_logo_url,
            'brandFavicon' => $branding?->flowtrack_brand_favicon_url,
            'footer' => \App\Support\SupplierPortalFooter::normalize($branding?->flowtrack_portal_footer),
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email'=>['required','string','max:254'], 'password'=>['required','string']]);
        $identifier = Str::lower(trim($credentials['email']));
        $field = filter_var($identifier, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';
        // Count FAILED attempts only: 5 per account (across IPs), plus a 30-failure IP ceiling.
        // Hash identifiers to avoid storing usernames/emails in the cache key.
        $accountKey = 'supplier-login:account:'.hash('sha256', $identifier);
        $ipKey = 'supplier-login:ip:'.hash('sha256', (string) $request->ip());
        if (RateLimiter::tooManyAttempts($accountKey, 5) || RateLimiter::tooManyAttempts($ipKey, 30)) {
            $wait = max(RateLimiter::availableIn($accountKey), RateLimiter::availableIn($ipKey));
            $exception = ValidationException::withMessages(['email' => 'Too many sign-in attempts. Please try again in '.$wait.' seconds.']);
            $exception->status = 429;
            throw $exception;
        }

        // Persistent remember-me cookies would defeat an enforced 30-minute idle timeout.
        $ok = Auth::attempt([$field=>$identifier, 'password'=>$credentials['password'], 'active'=>true], false);
        if (!$ok || !Auth::user()?->company?->active) {
            Auth::logout();
            RateLimiter::hit($accountKey, 60);
            RateLimiter::hit($ipKey, 60);
            throw ValidationException::withMessages(['email'=>'These credentials do not match an active supplier account.']);
        }
        RateLimiter::clear($accountKey);
        $request->session()->regenerate();
        $request->session()->put('supplier_last_activity_at', now()->timestamp);
        if ($request->user()->must_change_password) return redirect()->route('supplier.password.change');
        return redirect()->intended(route('supplier.home'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('supplier.login');
    }

    private function latestBrandFavicon(): ?string
    {
        return SupplierCompany::query()->where('active', true)->whereNotNull('flowtrack_brand_name')
            ->orderByDesc('brand_revision')->orderByDesc('updated_at')->orderByDesc('id')
            ->value('flowtrack_brand_favicon_url');
    }

    public function forgotForm() { return view('auth.forgot', ['brandFavicon' => $this->latestBrandFavicon()]); }

    public function sendReset(Request $request)
    {
        $request->validate(['email'=>['required','email','max:254']]);
        // Never disclose whether an email exists in this invitation-only application.
        Password::broker('supplier_users')->sendResetLink(['email'=>Str::lower(trim($request->email))]);
        return back()->with('status','If this address has an active supplier account, a reset link has been sent.');
    }

    public function resetForm(Request $request, string $token)
    {
        return view('auth.reset', ['token'=>$token, 'email'=>$request->query('email', ''), 'brandFavicon' => $this->latestBrandFavicon()]);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate([
            'token'=>['required','string'], 'email'=>['required','email'],
            'password'=>['required','confirmed', PasswordRule::min(8)],
        ]);
        $status = Password::broker('supplier_users')->reset($data, function ($user, $password) {
            if (!$user->active || !$user->company?->active) return;
            $user->forceFill(['password'=>Hash::make($password),'remember_token'=>Str::random(60),'must_change_password'=>false])->save();
            event(new PasswordReset($user));
        });
        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors(['email'=>'This reset link is invalid or expired.']);
        }
        return redirect()->route('supplier.login')->with('status','Your password has been updated.');
    }
    /** Forced password change after an administrator issues a temporary password. */
    public function changeRequiredForm()
    {
        return view('auth.change-password', ['brandFavicon' => auth()->user()?->company?->flowtrack_brand_favicon_url]);
    }

    public function changeRequiredPassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', 'different:current_password', PasswordRule::min(8)],
        ]);
        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'must_change_password' => false,
            'remember_token' => Str::random(60),
        ])->save();
        $request->session()->regenerate();
        return redirect()->route('supplier.home')->with('status', 'Password changed successfully.');
    }
}
