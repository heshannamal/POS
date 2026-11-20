<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Role;
use App\Models\StaffInvitation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class EmployeeController extends Controller
{
    public function index(Tenant $tenant): View
    {
        $employees = $tenant->users()->orderBy('name')->get();
        $assignedRoles = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.tenant_id', $tenant->id)
            ->select('role_user.user_id', 'roles.name')
            ->get()->groupBy('user_id');
        $roles = Role::where('tenant_id', $tenant->id)->orderBy('name')->get();
        $invitations = StaffInvitation::where('tenant_id', $tenant->id)
            ->whereNull('accepted_at')->where('expires_at', '>', now())
            ->with('role')->latest()->get();

        return view('business.employees.index', compact('tenant', 'employees', 'assignedRoles', 'roles', 'invitations'));
    }

    public function invite(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:191'],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')
                ->where(fn ($query) => $query->where('tenant_id', $tenant->id)->where('slug', '!=', 'owner'))],
        ]);
        $email = mb_strtolower(trim($data['email']));
        if ($tenant->users()->where('email', $email)->exists()) {
            throw ValidationException::withMessages(['email' => 'This user is already a member of this business.']);
        }
        if ($tenant->effective_status !== 'trial' && $tenant->effective_status !== 'active') {
            abort(403);
        }

        $subscription = $tenant->subscription?->plan;
        if ($subscription?->max_employees !== null) {
            $activeMembers = $tenant->users()->wherePivot('status', 'active')->count();
            $pendingInvites = StaffInvitation::where('tenant_id', $tenant->id)->whereNull('accepted_at')
                ->where('expires_at', '>', now())->distinct('email')->count('email');
            abort_if($activeMembers + $pendingInvites >= $subscription->max_employees, 422, 'Employee limit reached for the current plan.');
        }

        $token = Str::random(64);
        DB::transaction(function () use ($tenant, $data, $email, $token, $request) {
            StaffInvitation::where('tenant_id', $tenant->id)
                ->where('email', $email)->whereNull('accepted_at')->delete();

            StaffInvitation::create([
                'tenant_id' => $tenant->id, 'role_id' => $data['role_id'],
                'email' => $email, 'token_hash' => hash('sha256', $token),
                'invited_by' => $request->user()->id, 'expires_at' => now()->addDays(7),
            ]);
            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'employee.invited', 'metadata' => ['email' => $email, 'role_id' => (int) $data['role_id']],
                'ip_address' => $request->ip(),
            ]);
        });

        $url = route('staff.invites.show', $token);
        try {
            Mail::raw("You have been invited to join {$tenant->name} on POS Cloud.\n\nAccept your invitation: {$url}\n\nThis link expires in 7 days. Sign in or create an account using {$email}.", function ($message) use ($email, $tenant) {
                $message->to($email)->subject("Invitation to {$tenant->name} — POS Cloud");
            });
        } catch (\Throwable $e) {
            report($e);
            return back()->withErrors(['email' => 'The invitation was created, but the email could not be sent. Check mail settings and retry.']);
        }

        return back()->with('status', 'Invitation sent to '.$email.'.');
    }

    public function update(Request $request, Tenant $tenant, User $employee): RedirectResponse
    {
        $member = $tenant->users()->whereKey($employee->id)->firstOrFail();
        abort_if($member->pivot->is_owner || $request->user()->id === $employee->id, 403);

        $data = $request->validate([
            'status' => ['required', Rule::in(['active', 'suspended'])],
            'role_id' => ['required', 'integer', Rule::exists('roles', 'id')
                ->where(fn ($query) => $query->where('tenant_id', $tenant->id)->where('slug', '!=', 'owner'))],
        ]);

        DB::transaction(function () use ($tenant, $employee, $data, $request) {
            $tenant->users()->updateExistingPivot($employee->id, ['status' => $data['status']]);
            DB::table('role_user')->where('tenant_id', $tenant->id)->where('user_id', $employee->id)->delete();
            DB::table('role_user')->insert([
                'role_id' => $data['role_id'], 'tenant_id' => $tenant->id, 'user_id' => $employee->id,
            ]);
            AuditLog::create([
                'tenant_id' => $tenant->id, 'actor_id' => $request->user()->id,
                'action' => 'employee.updated', 'subject_type' => User::class,
                'subject_id' => $employee->id, 'metadata' => $data, 'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Employee access updated.');
    }
}
