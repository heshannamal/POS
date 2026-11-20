<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LoginEvent;
use App\Models\PlatformSetting;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function loginForm(): View
    {
        return view('auth.login');
    }

    public function registerForm(): View
    {
        abort_unless(PlatformSetting::registrationEnabled(), 403, 'Public registration is currently closed.');
        return view('auth.register');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            $matched = User::where('email', mb_strtolower(trim($credentials['email'])))->first();
            LoginEvent::create([
                'user_id' => $matched?->id,
                'email_hash' => hash('sha256', mb_strtolower(trim($credentials['email']))),
                'outcome' => 'failed',
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ]);
            return back()->withErrors(['email' => 'These credentials do not match our records.'])
                ->onlyInput('email');
        }

        if ($request->user()->isTemporaryAdminExpired()) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()->withErrors([
                'email' => 'This temporary login has expired. Generate a new one from your local terminal.',
            ])->onlyInput('email');
        }

        if ($request->user()->force_password_reset_at !== null) {
            LoginEvent::create([
                'user_id' => $request->user()->id,
                'outcome' => 'reset_required',
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ]);
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('password.request')->with(
                'status', 'A password reset is required. Request a secure email link to regain access.'
            );
        }

        $user = $request->user();
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        LoginEvent::create([
            'user_id' => $user->id,
            'email_hash' => hash('sha256', mb_strtolower($user->email)),
            'outcome' => 'success',
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
        ]);
        $request->session()->regenerate();
        $request->session()->put('account_session_version', (int) $user->auth_session_version);

        return redirect()->intended($this->homeFor($user));
    }

    public function register(Request $request): RedirectResponse
    {
        abort_unless(PlatformSetting::registrationEnabled(), 403, 'Public registration is currently closed.');
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->numbers()],
        ]);

        $user = User::create($validated);
        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('account_session_version', (int) $user->auth_session_version);

        return redirect()->route('verification.notice');
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    private function homeFor(User $user): string
    {
        if (! $user->hasVerifiedEmail()) {
            return route('verification.notice');
        }

        if (session()->has('pending_staff_invite')) {
            return route('staff.invites.show', session()->get('pending_staff_invite'));
        }

        if ($user->is_platform_admin) {
            return route('platform.dashboard');
        }

        $tenant = $user->tenants()->wherePivot('status', 'active')->first();

        return $tenant
            ? route('business.dashboard', $tenant)
            : route('onboarding.create');
    }
}
