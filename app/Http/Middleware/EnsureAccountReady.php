<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountReady
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $boundId = $request->session()->get('account_session_user_id');
        if ($user && $request->session()->has('account_session_version')
            && ($boundId === null || (int) $boundId === $user->id)
            && (int) $request->session()->get('account_session_version')
                !== (int) $user->auth_session_version) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('status', 'This session was revoked. Sign in again.');
        }

        if ($user?->force_password_reset_at !== null) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('password.request')->with(
                'status',
                'An administrator requires a secure password reset before your account can be used. Request a password reset link.'
            );
        }

        if ($user && $user->is_platform_admin) {
            $factor = app(\App\Services\PlatformTwoFactor::class);
            if ($factor->required($user) && ! $factor->verified($request)) {
                return redirect()->to($factor->destination($request));
            }
        }
        if ($user && ! app(\App\Services\UserSessionTracker::class)->track($request)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('login')->with('status', 'Session expired or revoked. Sign in again.');
        }

        $days = (int) config('platform_security.password_expiry_days', 0);
        if ($user && $days > 0 && $user->password_changed_at
            && $user->password_changed_at->lt(now()->subDays($days))) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return redirect()->route('password.request')->with(
                'status', 'Your password has expired. Request a secure reset link.'
            );
        }
        return $next($request);
    }
}
