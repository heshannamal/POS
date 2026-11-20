<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\StaffInvitation;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class StaffInvitationController extends Controller
{
    private function invitation(string $token): StaffInvitation
    {
        return StaffInvitation::with('tenant', 'role')
            ->where('token_hash', hash('sha256', $token))
            ->whereNull('accepted_at')->where('expires_at', '>', now())->firstOrFail();
    }

    public function show(Request $request, string $token): View
    {
        $invitation = $this->invitation($token);
        $request->session()->put('pending_staff_invite', $token);

        return view('auth.staff-invitation', compact('invitation', 'token'));
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = DB::transaction(function () use ($request, $token) {
            $invite = StaffInvitation::where('token_hash', hash('sha256', $token))
                ->lockForUpdate()->firstOrFail();
            abort_if($invite->accepted_at || $invite->expires_at->isPast(), 410);
            abort_unless(
                mb_strtolower($request->user()->email) === mb_strtolower($invite->email)
                && $request->user()->hasVerifiedEmail(),
                403
            );
            abort_unless($invite->role->tenant_id === $invite->tenant_id && $invite->role->slug !== 'owner', 403);
            abort_unless(in_array($invite->tenant->effective_status, ['trial', 'active']), 403);

            $existing = $invite->tenant->users()->whereKey($request->user()->id)->first();
            abort_if($existing && $existing->pivot->status !== 'active', 403);

            if (! $existing) {
                $invite->tenant->users()->attach($request->user()->id, ['status' => 'active', 'is_owner' => false]);
            }
            abort_if($existing && $existing->pivot->is_owner, 403);

            DB::table('role_user')->where('tenant_id', $invite->tenant_id)
                ->where('user_id', $request->user()->id)->delete();
            DB::table('role_user')->insert([
                'tenant_id' => $invite->tenant_id,
                'user_id' => $request->user()->id,
                'role_id' => $invite->role_id,
            ]);

            $invite->update(['accepted_at' => now()]);
            AuditLog::create([
                'tenant_id' => $invite->tenant_id, 'actor_id' => $request->user()->id,
                'action' => 'employee.invitation.accepted', 'subject_type' => User::class,
                'subject_id' => $request->user()->id, 'ip_address' => $request->ip(),
            ]);

            return $invite;
        });

        $request->session()->forget('pending_staff_invite');

        return redirect()->route('business.dashboard', $invitation->tenant)
            ->with('status', 'You have joined '.$invitation->tenant->name.'.');
    }
}
