<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\PlatformUserInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class PlatformUserInvitationController extends Controller
{
    private function validInvitation(string $token): PlatformUserInvitation
    {
        return PlatformUserInvitation::where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')->where('expires_at', '>', now())->firstOrFail();
    }

    public function show(string $token): View
    {
        $invitation = $this->validInvitation($token);
        abort_if(User::where('email', $invitation->email)->exists(), 410);

        return view('auth.platform-invitation', compact('invitation', 'token'));
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'confirmed', Password::min((int) config('platform_security.password_min_length', 12))->letters()->numbers()],
        ]);

        $user = DB::transaction(function () use ($token, $data, $request) {
            $invitation = PlatformUserInvitation::where('token_hash', hash('sha256', $token))
                ->lockForUpdate()->firstOrFail();
            abort_if($invitation->accepted_at !== null || $invitation->expires_at->isPast(), 410);
            abort_if(User::where('email', $invitation->email)->exists(), 410);

            $user = User::create([
                'name' => $invitation->name, 'email' => $invitation->email,
                'password' => $data['password'],
            ]);
            // Possession of a one-time random email invitation link is the email proof.
            $user->forceFill(['email_verified_at' => now(), 'password_changed_at' => now()])->save();
            $invitation->update(['accepted_at' => now()]);
            AuditLog::create([
                'actor_id' => $user->id, 'action' => 'platform.user.invitation.accepted',
                'subject_type' => User::class, 'subject_id' => $user->id,
                'ip_address' => $request->ip(),
            ]);
            return $user;
        });

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('account_session_version', (int) $user->auth_session_version);
        $request->session()->put('account_session_user_id', $user->id);

        return redirect()->route('onboarding.create')
            ->with('status', 'Welcome. Your account is verified. You can now create your business.');
    }
}
