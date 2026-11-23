<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\LoginEvent;
use App\Models\SecurityAlert;
use App\Models\User;
use App\Models\UserSecuritySession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SecurityDashboardController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:120'],
            'outcome' => ['nullable', Rule::in(['success', 'failed', 'reset_required', 'blocked'])],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);
        $events = LoginEvent::with('user:id,name,email')->latest('id')
            ->when($filters['outcome'] ?? null, fn ($q, $v) => $q->where('outcome', $v))
            ->when($filters['from'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when($filters['to'] ?? null, fn ($q, $v) => $q->whereDate('created_at', '<=', $v));
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $events->where(fn ($q) => $q->where('ip_address', 'like', '%'.$search.'%')
                ->orWhereHas('user', fn ($u) => $u->where('email', 'like', '%'.$search.'%')));
        }
        $logins = $events->paginate(25)->withQueryString();
        $alerts = SecurityAlert::with('user:id,name,email')->latest('id')->limit(20)->get();
        $stats = [
            'failed_today' => LoginEvent::where('outcome', 'failed')->whereDate('created_at', today())->count(),
            'unresolved_alerts' => SecurityAlert::whereNull('resolved_at')->count(),
            'active_sessions' => UserSecuritySession::whereNull('revoked_at')->where('last_seen_at', '>=',
                now()->subMinutes((int) config('platform_security.session_idle_minutes', 60)))->count(),
            'unprotected_admins' => User::where('is_platform_admin', true)
                ->whereNull('temporary_admin_expires_at')->whereNull('two_factor_confirmed_at')->count(),
        ];

        return view('platform.security.index', compact('logins', 'alerts', 'stats'));
    }

    public function resolve(Request $request, SecurityAlert $alert): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);
        if (! $alert->resolved_at) {
            $alert->update(['resolved_at' => now(), 'resolved_by' => $request->user()->id]);
            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'security.alert.resolved',
                'subject_type' => SecurityAlert::class, 'subject_id' => $alert->id,
                'metadata' => ['reason' => $data['reason']],
                'ip_address' => $request->ip(),
            ]);
        }
        return back()->with('status', 'Alert reviewed and marked resolved.');
    }
}
