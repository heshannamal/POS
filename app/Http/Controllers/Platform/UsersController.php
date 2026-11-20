<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\PosEmailVariables;
use App\Models\PlatformUserInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UsersController extends Controller
{
    public function index(Request $request): View
    {
        $query = User::withCount('tenants')->orderByDesc('id');
        if ($term = trim((string) $request->query('search', ''))) {
            $query->where(fn ($q) => $q->where('email', 'like', '%'.$term.'%')
                ->orWhere('name', 'like', '%'.$term.'%'));
        }
        if ($request->query('verified') === 'yes') {
            $query->whereNotNull('email_verified_at');
        } elseif ($request->query('verified') === 'no') {
            $query->whereNull('email_verified_at');
        }
        $users = $query->paginate(20)->withQueryString();
        $invitations = PlatformUserInvitation::whereNull('accepted_at')
            ->where('expires_at', '>', now())->with('inviter:id,name')
            ->latest('id')->limit(15)->get();

        return view('platform.users.index', compact('users', 'invitations'));
    }

    public function show(User $user): View
    {
        $user->load('tenants.trial', 'tenants.subscription.plan');
        $roles = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)->get([
                'role_user.tenant_id', 'roles.name',
            ])->groupBy('tenant_id');
        $activity = AuditLog::where('actor_id', $user->id)->latest('id')->limit(15)->get();

        return view('platform.users.show', compact('user', 'roles', 'activity'));
    }

    public function invite(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:191'],
        ]);
        $email = mb_strtolower(trim($data['email']));
        if (User::where('email', $email)->exists()) {
            throw ValidationException::withMessages([
                'email' => 'This email already belongs to a registered user. Open the user directory instead.',
            ]);
        }
        $token = Str::random(64);

        DB::transaction(function () use ($request, $data, $email, $token): void {
            // Re-inviting rotates the token. Old links immediately become invalid.
            PlatformUserInvitation::where('email', $email)->whereNull('accepted_at')->delete();
            $invite = PlatformUserInvitation::create([
                'name' => $data['name'], 'email' => $email,
                'token_hash' => hash('sha256', $token), 'invited_by' => $request->user()->id,
                'expires_at' => now()->addDays(7),
            ]);
            AuditLog::create([
                'actor_id' => $request->user()->id, 'action' => 'platform.user.invited',
                'subject_type' => PlatformUserInvitation::class, 'subject_id' => $invite->id,
                'metadata' => ['email' => $email], 'ip_address' => $request->ip(),
            ]);
        });

        $url = route('platform-invite.show', $token);
        $vars = [
            'platform_name' => config('app.name', 'POS Cloud'),
            'user_name' => $data['name'],
            'user_email' => $email,
            'invitation_url' => $url,
            'expires_days' => '7',
        ];
        try {
            Mail::raw(PosEmailVariables::render('account_invitation', 'body', $vars), function ($message) use ($email, $vars) {
                $message->to($email)->subject(PosEmailVariables::render('account_invitation', 'subject', $vars));
            });
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors([
                'email' => 'The invitation was saved, but the email could not be sent. Check your mail configuration and re-invite.',
            ]);
        }
        return back()->with('status', 'Invitation sent. The new user can set their password using the emailed link.');
    }
}
