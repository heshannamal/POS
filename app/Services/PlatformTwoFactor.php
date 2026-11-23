<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;

class PlatformTwoFactor
{
    public function required(User $user): bool
    {
        return $user->is_platform_admin && (bool) config('platform_security.require_2fa', false);
    }

    public function enrolled(User $user): bool
    {
        return $this->required($user);
    }

    public function verified(Request $request): bool
    {
        $user = $request->user();
        if (! $user) return false;

        $stamp = (int) $request->session()->get('platform_2fa_verified_at', 0);
        return (int) $request->session()->get('platform_2fa_verified_user', 0) === $user->id
            && $stamp > 0
            && $stamp >= now()->subMinutes((int) config('platform_security.verification_window_minutes', 720))->timestamp;
    }

    public function markVerified(Request $request): void
    {
        $request->session()->regenerate();
        $request->session()->put('account_session_version', (int) $request->user()->auth_session_version);
        $request->session()->put('account_session_user_id', $request->user()->id);
        $request->session()->put('platform_2fa_verified_user', $request->user()->id);
        $request->session()->put('platform_2fa_verified_at', now()->timestamp);
    }

    public function destination(Request $request): string
    {
        return route('security.challenge');
    }
}
