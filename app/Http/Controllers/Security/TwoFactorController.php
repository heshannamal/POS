<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\LoginEvent;
use App\Models\SecurityAlert;
use App\Models\User;
use App\Services\EmailLoginCode;
use App\Services\PlatformTwoFactor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    private function admin(Request $request): User
    {
        $user = $request->user();
        abort_unless($user?->is_platform_admin
            && ! $user->isTemporaryAdminExpired()
            && $user->force_password_reset_at === null
            && $request->session()->has('account_session_version')
            && (int) $request->session()->get('account_session_version') === (int) $user->auth_session_version
            && (int) $request->session()->get('account_session_user_id') === (int) $user->id, 403);
        return $user;
    }

    public function challenge(Request $request, PlatformTwoFactor $factor): View|RedirectResponse
    {
        $user = $this->admin($request);
        if (! $factor->required($user) || $factor->verified($request)) {
            return redirect()->route('platform.dashboard');
        }
        return view('security.challenge', ['email' => $user->email]);
    }

    public function resend(Request $request, EmailLoginCode $codes): RedirectResponse
    {
        $user = $this->admin($request);
        abort_unless(app(PlatformTwoFactor::class)->required($user), 403);
        try {
            $codes->send($user, $request->ip());
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['code' => 'Unable to send verification email. Check SMTP settings and try again.']);
        }
        return back()->with('status', 'A fresh verification code was sent to your email address.');
    }

    public function verify(Request $request, EmailLoginCode $codes, PlatformTwoFactor $factor): RedirectResponse
    {
        $user = $this->admin($request);
        abort_unless($factor->required($user), 403);
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        try {
            $codes->verify($user, $data['code'], $request->ip());
        } catch (ValidationException $e) {
            SecurityAlert::create(['user_id' => $user->id, 'type' => 'two_factor_failure',
                'ip_address' => $request->ip(), 'metadata' => ['method' => 'email']]);
            throw $e;
        }
        $factor->markVerified($request);
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        LoginEvent::create(['user_id' => $user->id, 'outcome' => 'success',
            'email_hash' => hash('sha256', mb_strtolower($user->email)),
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 500, '')]);
        return redirect()->intended(route('platform.dashboard'))
            ->with('status', 'Email verification completed.');
    }
}
