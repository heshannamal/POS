<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\PlatformSetting;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rules\Password;
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

        $request->session()->regenerate();

        return redirect()->intended($this->homeFor($request->user()));
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
