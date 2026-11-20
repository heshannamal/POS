<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TrialManagementController extends Controller
{
    public function index(Request $request): View
    {
        $state = $request->query('state', 'running');
        $query = Tenant::query()->with(['trial', 'subscription.plan'])
            ->whereHas('trial');

        if ($state === 'running') {
            $query->where('status', 'trial')->whereHas('trial', fn ($q) => $q->where('ends_at', '>', now()));
        } elseif ($state === 'expired') {
            $query->where('status', 'trial')->whereHas('trial', fn ($q) => $q->where('ends_at', '<=', now()));
        } elseif ($state === 'converted') {
            $query->whereHas('trial', fn ($q) => $q->whereNotNull('converted_at'));
        } elseif ($state === 'suspended') {
            $query->where('status', 'suspended');
        } elseif ($state !== 'all') {
            $state = 'running';
            $query->where('status', 'trial')->whereHas('trial', fn ($q) => $q->where('ends_at', '>', now()));
        }
        if ($term = trim((string) $request->query('search', ''))) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')
                ->orWhere('contact_email', 'like', '%'.$term.'%'));
        }
        $tenants = $query->latest()->paginate(15)->withQueryString();
        $defaultDays = PlatformSetting::trialDays();

        return view('platform.trials.index', compact('tenants', 'defaultDays', 'state'));
    }

    public function settings(Request $request): RedirectResponse
    {
        $data = $request->validate(['default_days' => ['required', 'integer', 'min:1', 'max:90']]);
        PlatformSetting::updateOrCreate(['key' => 'trial.default_days'], ['value' => (string) $data['default_days']]);
        AuditLog::create([
            'actor_id' => $request->user()->id, 'action' => 'trial.settings.updated',
            'metadata' => ['default_days' => (int) $data['default_days']],
            'ip_address' => $request->ip(),
        ]);
        return back()->with('status', 'Trial duration updated for newly registered businesses.');
    }
}
