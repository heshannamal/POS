<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubscriptionRegistryController extends Controller
{
    public function index(Request $request): View
    {
        $query = Subscription::with(['tenant.trial', 'plan'])->latest();
        if ($status = $request->query('status')) {
            if (in_array($status, ['trialing', 'active', 'paused', 'past_due', 'expired', 'cancelled'], true)) {
                $query->where('status', $status);
            }
        }
        if ($plan = $request->query('plan')) {
            $query->whereHas('plan', fn ($q) => $q->where('slug', $plan));
        }
        if ($search = trim((string) $request->query('search', ''))) {
            $query->whereHas('tenant', fn ($q) => $q->where('name', 'like', '%'.$search.'%')
                ->orWhere('slug', 'like', '%'.$search.'%'));
        }

        $subscriptions = $query->paginate(20)->withQueryString();
        $plans = Plan::orderBy('name')->get();
        $stats = [
            'all' => Subscription::count(),
            'active' => Subscription::where('status', 'active')->count(),
            'trialing' => Subscription::where('status', 'trialing')->count(),
            'paused' => Subscription::where('status', 'paused')->count(),
        ];

        return view('platform.subscriptions.index', compact('subscriptions', 'plans', 'stats'));
    }
}
