<?php

namespace App\Http\Controllers\Security;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\SecurityAlert;
use App\Models\User;
use App\Services\PlatformTwoFactor;
use App\Services\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user && $user->is_platform_admin
            && ! $user->isTemporaryAdminExpired(), 403);
        return $user;
    }

    public function setup(Request $request, Totp $totp): View|RedirectResponse
    {
        $user = $this->user($request);
        if ($user->two_factor_confirmed_at) {
            return redirect()->route('security.challenge');
        }

        if (! $request->session()->has('pending_two_factor_secret')) {
            $request->session()->put('pending_two_factor_secret', $totp->generateSecret());
        }
        $secret = $request->session()->get('pending_two_factor_secret');

        return view('security.setup', [
            'secret' => $secret,
            'uri' => $totp->uri($secret, $user->email),
            'recoveryCodes' => $request->session()->pull('new_recovery_codes', []),
        ]);
    }

    public function enable(Request $request, Totp $totp, PlatformTwoFactor $factor): RedirectResponse
    {
        $user = $this->user($request);
        abort_if($user->two_factor_confirmed_at !== null, 409);

        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);
        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => 'Current password is incorrect.']);
        }
        $secret = $request->session()->get('pending_two_factor_secret');
        if (! is_string($secret) || ! $totp->verify($secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Invalid authenticator code. Check the device clock.']);
        }

        $plain = $totp->recoveryCodes();
        DB::transaction(function () use ($user, $secret, $plain, $request): void {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_if($locked->two_factor_confirmed_at !== null, 409);
            $locked->forceFill([
                'two_factor_secret' => $secret,
                'two_factor_recovery_codes' => array_map(
                    fn (string $code) => Hash::make($code), $plain
                ),
                'two_factor_confirmed_at' => now(),
            ])->save();
            AuditLog::create([
                'actor_id' => $locked->id,
                'action' => 'security.two_factor.enabled',
                'subject_type' => User::class, 'subject_id' => $locked->id,
                'ip_address' => $request->ip(),
            ]);
        });

        $request->session()->forget('pending_two_factor_secret');
        $factor->markVerified($request);
        $request->session()->flash('new_recovery_codes', $plain);

        return redirect()->route('security.recovery')
            ->with('status', 'Authenticator 2FA enabled. Save your recovery codes before continuing.');
    }

    public function challenge(Request $request, PlatformTwoFactor $factor): View|RedirectResponse
    {
        $user = $this->user($request);
        if (! $factor->enrolled($user)) {
            return redirect()->route('security.setup');
        }
        if ($factor->verified($request)) {
            return redirect()->route('platform.dashboard');
        }
        return view('security.challenge');
    }

    public function verify(Request $request, Totp $totp, PlatformTwoFactor $factor): RedirectResponse
    {
        $user = $this->user($request);
        if (! $factor->enrolled($user)) {
            return redirect()->route('security.setup');
        }
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:20'],
            'recovery_code' => ['nullable', 'string', 'max:30'],
        ]);
        $code = trim((string) ($data['code'] ?? ''));
        $recovery = strtoupper(trim((string) ($data['recovery_code'] ?? '')));
        if (($code === '' && $recovery === '') || ($code !== '' && $recovery !== '')) {
            throw ValidationException::withMessages(['code' => 'Enter one authenticator or recovery code.']);
        }

        $passed = false;
        if ($code !== '') {
            $passed = $totp->verify($user->two_factor_secret, $code);
        } elseif (preg_match('/^[A-F0-9]{10}$/', $recovery)) {
            DB::transaction(function () use (&$passed, $user, $recovery, $request): void {
                $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
                $hashed = $locked->two_factor_recovery_codes ?? [];
                foreach ($hashed as $i => $hash) {
                    if (Hash::check($recovery, $hash)) {
                        unset($hashed[$i]);
                        $locked->forceFill(['two_factor_recovery_codes' => array_values($hashed)])->save();
                        AuditLog::create([
                            'actor_id' => $locked->id, 'action' => 'security.recovery_code.used',
                            'subject_type' => User::class, 'subject_id' => $locked->id,
                            'metadata' => ['remaining' => count($hashed)],
                            'ip_address' => $request->ip(),
                        ]);
                        $passed = true;
                        break;
                    }
                }
            });
        }
        if (! $passed) {
            SecurityAlert::create([
                'user_id' => $user->id, 'type' => 'two_factor_failure',
                'ip_address' => $request->ip(), 'metadata' => ['route' => 'challenge'],
            ]);
            throw ValidationException::withMessages(['code' => 'Invalid or already used verification code.']);
        }

        $factor->markVerified($request);
        AuditLog::create([
            'actor_id' => $user->id, 'action' => 'security.two_factor.verified',
            'subject_type' => User::class, 'subject_id' => $user->id,
            'ip_address' => $request->ip(),
        ]);

        return redirect()->route('platform.dashboard')->with('status', 'Two-factor authentication verified.');
    }

    public function recovery(Request $request, PlatformTwoFactor $factor): View|RedirectResponse
    {
        $user = $this->user($request);
        if (! $factor->enrolled($user) || ! $factor->verified($request)) {
            return redirect()->to($factor->destination($request));
        }
        $codes = $request->session()->pull('new_recovery_codes', []);
        return view('security.recovery', compact('codes'));
    }

    public function regenerate(Request $request, Totp $totp, PlatformTwoFactor $factor): RedirectResponse
    {
        $user = $this->user($request);
        abort_unless($factor->enrolled($user) && $factor->verified($request), 403);

        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);
        if (! Hash::check($data['password'], $user->password) ||
            ! $totp->verify($user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Invalid password or authenticator code.']);
        }
        $plain = $totp->recoveryCodes();
        DB::transaction(function () use ($user, $request, $plain): void {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail()->forceFill([
                'two_factor_recovery_codes' => array_map(fn ($code) => Hash::make($code), $plain),
            ])->save();
            AuditLog::create([
                'actor_id' => $user->id, 'action' => 'security.recovery_codes.regenerated',
                'subject_type' => User::class, 'subject_id' => $user->id,
                'ip_address' => $request->ip(),
            ]);
        });
        $request->session()->flash('new_recovery_codes', $plain);
        return redirect()->route('security.recovery')->with('status', 'Old recovery codes invalidated. Store the replacements now.');
    }

    public function disable(Request $request, Totp $totp): RedirectResponse
    {
        $user = $this->user($request);
        if ($user->isPermanentPlatformAdmin() && $user->platformRole() === 'super_admin'
            && config('platform_security.require_2fa', false)) {
            throw ValidationException::withMessages([
                'code' => 'Two-factor authentication is mandatory for permanent Super Administrators.',
            ]);
        }
        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'digits:6'],
        ]);
        if (! Hash::check($data['password'], $user->password) ||
            ! $totp->verify((string) $user->two_factor_secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => 'Invalid password or authenticator code.']);
        }

        DB::transaction(function () use ($user, $request): void {
            User::whereKey($user->id)->lockForUpdate()->firstOrFail()->forceFill([
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();
            AuditLog::create([
                'actor_id' => $user->id, 'action' => 'security.two_factor.disabled',
                'subject_type' => User::class, 'subject_id' => $user->id,
                'ip_address' => $request->ip(),
            ]);
        });
        $request->session()->forget(['platform_2fa_verified_user', 'platform_2fa_verified_at']);

        return redirect()->route('security.setup')->with('status', 'Authenticator removed. You can set up another one.');
    }
}
