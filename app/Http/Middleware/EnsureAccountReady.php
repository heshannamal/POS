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
        if ($user && (int) $request->session()->get('account_session_version', 0)
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

        return $next($request);
    }
}
