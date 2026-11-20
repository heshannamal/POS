<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\SupportTicket;
use App\Models\AuditLog;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Branch;
use App\Models\SubscriptionPaymentRequest;
use Illuminate\Support\Facades\DB;
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

        $stats['users'] = User::count();
        $stats['stores'] = Branch::count();
        $stats['pending_payments'] = SubscriptionPaymentRequest::where('status', 'pending')->count();
        $stats['verified_today'] = SubscriptionPaymentRequest::where('status', 'approved')
            ->whereDate('reviewed_at', today())->count();

        $verifiedAmounts = SubscriptionPaymentRequest::query()
            ->where('status', 'approved')
            ->select('currency')
            ->selectRaw('COALESCE(SUM(approved_amount_minor), 0) as total_minor')
            ->groupBy('currency')->orderBy('currency')->get();

        try {
            DB::select('select 1');
            $databaseHealthy = true;
        } catch (\Throwable $e) {
            report($e);
            $databaseHealthy = false;
        }
        $health = [
            'app' => 'Running',
            'database' => $databaseHealthy ? 'Connected' : 'Unavailable',
            'storage' => is_writable(storage_path('app/private')) ? 'Writable' : 'Not writable',
            'api' => 'Not connected',
            'uptime' => 'Not instrumented',
        ];

        $recent = Tenant::with('trial')->withCount('users')
            ->latest()->limit(6)->get();

        $expiring = Tenant::with('trial')->where('status', 'trial')
            ->whereHas('trial', fn ($q) => $q->whereBetween('ends_at', [now(), now()->addDays(7)]))
            ->orderBy(
                \App\Models\Trial::select('ends_at')
                    ->whereColumn('trials.tenant_id', 'tenants.id')->limit(1)
            )->limit(5)->get();

        $stats['support'] = SupportTicket::whereNotIn('status', ['closed','resolved'])->count();
        $stats['subscriptions'] = Subscription::where('source', 'manual')->count();
        $recentActions = AuditLog::with('actor:id,name')->latest('id')->limit(5)->get();

        return view('platform.dashboard', compact('stats', 'recent', 'expiring', 'recentActions', 'verifiedAmounts', 'health'));
    }
}
