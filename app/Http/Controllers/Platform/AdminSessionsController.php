<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\UserSecuritySession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AdminSessionsController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'status' => ['nullable', 'in:active,revoked'],
        ]);
        $sessions = UserSecuritySession::with('user:id,name,email')
            ->when($filters['search'] ?? null, fn ($q, $s) =>
                $q->where(fn ($search) => $search->where('ip_address', 'like', '%'.$s.'%')
                    ->orWhereHas('user', fn ($user) => $user->where('email', 'like', '%'.$s.'%'))))
            ->when($filters['status'] ?? null, fn ($q, $v) =>
                $v === 'active' ? $q->whereNull('revoked_at') : $q->whereNotNull('revoked_at'))
            ->latest('last_seen_at')->paginate(25)->withQueryString();

        return view('platform.security.sessions', compact('sessions'));
    }

    public function revoke(Request $request, UserSecuritySession $session): RedirectResponse
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'reason' => ['required', 'string', 'min:10', 'max:1000'],
        ]);
        if (! Hash::check($data['password'], $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Administrator password is incorrect.']);
        }
        if ($session->user_id === $request->user()->id) {
            throw ValidationException::withMessages([
                'reason' => 'Use My Sessions to revoke your own sessions.',
            ]);
        }
        if (! $session->revoked_at) {
            $session->update(['revoked_at' => now()]);
            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'security.session.admin_revoked',
                'subject_type' => UserSecuritySession::class,
                'subject_id' => $session->id,
                'metadata' => ['target_user_id' => $session->user_id, 'reason' => $data['reason']],
                'ip_address' => $request->ip(),
            ]);
        }
        return back()->with('status', 'Selected user session revoked on its next request.');
    }
}
