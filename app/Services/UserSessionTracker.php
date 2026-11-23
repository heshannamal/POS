<?php

namespace App\Services;

use App\Models\UserSecuritySession;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UserSessionTracker
{
    public function hash(Request $request): string
    {
        return hash('sha256', (string) $request->session()->getId());
    }

    /** Return false for revoked or idle sessions. */
    public function track(Request $request): bool
    {
        $user = $request->user();
        if (! $user) return true;

        $hash = $this->hash($request);
        $session = UserSecuritySession::firstOrCreate([
            'user_id' => $user->id,
            'session_hash' => $hash,
        ], [
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            'last_seen_at' => now(),
        ]);

        if ($session->revoked_at) return false;
        $idle = (int) config('platform_security.session_idle_minutes', 60);
        if ($session->last_seen_at->lt(now()->subMinutes($idle))) {
            $session->update(['revoked_at' => now()]);
            return false;
        }
        if ($session->last_seen_at->lt(now()->subMinute())) {
            $session->update([
                'last_seen_at' => now(),
                'ip_address' => $request->ip(),
                'user_agent' => Str::limit((string) $request->userAgent(), 500, ''),
            ]);
        }
        return true;
    }
}
