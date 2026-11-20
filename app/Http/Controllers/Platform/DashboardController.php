<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $stats = [
            'businesses' => Tenant::count(),
            'active' => Tenant::where('status', 'active')->count(),
            'trial' => Tenant::where('status', 'trial')
                ->whereHas('trial', fn ($q) => $q->where('ends_at', '>', now()))->count(),
            'expired' => Tenant::where('status', 'trial')
                ->whereHas('trial', fn ($q) => $q->where('ends_at', '<=', now()))->count(),
        ];

        $recent = Tenant::with('trial')->withCount('users')
            ->latest()->limit(6)->get();

        $expiring = Tenant::with('trial')->where('status', 'trial')
            ->whereHas('trial', fn ($q) => $q->whereBetween('ends_at', [now(), now()->addDays(7)]))
            ->orderBy(
                \App\Models\Trial::select('ends_at')
                    ->whereColumn('trials.tenant_id', 'tenants.id')->limit(1)
            )->limit(5)->get();

        return view('platform.dashboard', compact('stats', 'recent', 'expiring'));
    }
}
