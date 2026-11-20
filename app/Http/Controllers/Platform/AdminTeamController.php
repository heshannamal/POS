<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminTeamController extends Controller
{
    private const ROLES = ['super_admin', 'operations', 'billing', 'support', 'auditor'];

    public function index(Request $request): View
    {
        $administrators = User::where('is_platform_admin', true)
            ->orderBy('name')->paginate(15);
        $candidates = collect();
        if ($term = trim((string) $request->query('search', ''))) {
            $candidates = User::where('is_platform_admin', false)
                ->whereNotNull('email_verified_at')
                ->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')
                    ->orWhere('email', 'like', '%'.$term.'%'))
                ->orderBy('name')->limit(15)->get();
        }

        return view('platform.admins.index', [
            'administrators' => $administrators,
            'candidates' => $candidates,
            'roles' => self::ROLES,
        ]);
    }

    private function password(Request $request): void
    {
        $request->validate(['admin_password' => ['required', 'string']]);
        if (! Hash::check($request->input('admin_password'), $request->user()->password)) {
            throw ValidationException::withMessages([
                'admin_password' => 'The administrator password is incorrect.',
            ]);
        }
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:191', 'exists:users,email'],
            'role' => ['required', Rule::in(self::ROLES)],
            'admin_password' => ['required', 'string'],
        ]);
        $this->password($request);

        DB::transaction(function () use ($request, $data): void {
            $target = User::where('email', $data['email'])->lockForUpdate()->firstOrFail();
            abort_unless($target->hasVerifiedEmail() && ! $target->is_platform_admin
                && $target->temporary_admin_expires_at === null, 422);
            $target->forceFill(['is_platform_admin' => true, 'platform_role' => $data['role']])->save();

            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'platform.admin.granted', 'subject_type' => User::class,
                'subject_id' => $target->id, 'metadata' => ['role' => $data['role']],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Platform administrator access granted.');
    }

    public function update(Request $request, User $administrator): RedirectResponse
    {
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'role' => ['required', Rule::in(self::ROLES)],
            'admin_password' => ['required', 'string'],
        ]);
        $this->password($request);

        DB::transaction(function () use ($request, $administrator, $data): void {
            $target = User::whereKey($administrator->id)->lockForUpdate()->firstOrFail();
            abort_unless($target->is_platform_admin, 404);
            abort_if($target->id === $request->user()->id, 403, 'You cannot modify your own administrator access.');

            if ($target->platformRole() === 'super_admin'
                && (! $data['enabled'] || $data['role'] !== 'super_admin')) {
                $otherSuperAdmins = User::where('is_platform_admin', true)
                    ->whereNull('temporary_admin_expires_at')
                    ->where(fn ($q) => $q->where('platform_role', 'super_admin')->orWhereNull('platform_role'))
                    ->whereKeyNot($target->id)->count();
                abort_if($otherSuperAdmins < 1, 422, 'At least one permanent super administrator must remain.');
            }

            $before = $target->platformRole();
            $target->forceFill([
                'is_platform_admin' => (bool) $data['enabled'],
                'platform_role' => $data['enabled'] ? $data['role'] : null,
            ])->save();

            AuditLog::create([
                'actor_id' => $request->user()->id, 'action' => 'platform.admin.changed',
                'subject_type' => User::class, 'subject_id' => $target->id,
                'metadata' => [
                    'previous_role' => $before, 'new_role' => $data['enabled'] ? $data['role'] : null,
                    'enabled' => (bool) $data['enabled'],
                ],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Platform administrator access updated.');
    }
}
