<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\Tenant;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(): View
    {
        $now = now();
        $start = $now->copy()->startOfMonth()->subMonths(11);
        $signups = Tenant::where('created_at', '>=', $start)
            ->get(['id', 'created_at'])->groupBy(fn ($tenant) => $tenant->created_at->format('Y-m'))
            ->map->count();

        $months = [];
        for ($i = 0; $i < 12; $i++) {
            $date = $start->copy()->addMonths($i);
            $months[] = [
                'key' => $date->format('Y-m'),
                'label' => $date->format('M y'),
                'count' => $signups[$date->format('Y-m')] ?? 0,
            ];
        }

        $total = Tenant::count();
        $converted = Tenant::whereHas('trial', fn ($q) => $q->whereNotNull('converted_at'))->count();
        $stats = [
            'total' => $total,
            'new_month' => Tenant::where('created_at', '>=', $now->copy()->startOfMonth())->count(),
            'converted' => $converted,
            'conversion_rate' => $total ? round(($converted / $total) * 100, 1) : 0,
            'open_tickets' => SupportTicket::whereNotIn('status', ['closed', 'resolved'])->count(),
            'manual_subscriptions' => Subscription::where('source', 'manual')->count(),
            'activity_week' => AuditLog::where('created_at', '>=', $now->copy()->subWeek())->count(),
        ];

        $planTotals = Plan::query()->withCount('subscriptions')->orderBy('name')->get();
        $top = max(1, ...array_column($months, 'count'));

        return view('platform.reports.index', compact('months', 'stats', 'planTotals', 'top'));
    }
}
