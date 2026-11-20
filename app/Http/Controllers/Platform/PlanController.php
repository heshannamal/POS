<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Module;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PlanController extends Controller
{
    public function index(): View
    {
        $plans = Plan::with('modules')->orderBy('monthly_price_minor')->get();
        $modules = Module::orderBy('category')->orderBy('name')->get();

        return view('platform.plans.index', compact('plans', 'modules'));
    }

    public function create(): View
    {
        $modules = Module::orderBy('category')->orderBy('name')->get();

        return view('platform.plans.form', ['plan' => null, 'modules' => $modules]);
    }

    public function edit(Plan $plan): View
    {
        $plan->load('modules');
        $modules = Module::orderBy('category')->orderBy('name')->get();

        return view('platform.plans.form', compact('plan', 'modules'));
    }

    private function validated(Request $request, ?Plan $plan = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'alpha_dash', 'max:80', Rule::unique('plans', 'slug')->ignore($plan?->id)],
            'description' => ['nullable', 'string', 'max:1500'],
            'monthly_price' => ['required', 'numeric', 'min:0', 'max:10000000', 'decimal:0,2'],
            'currency' => ['required', Rule::in(['LKR', 'USD'])],
            'is_active' => ['required', 'boolean'],
            'is_public' => ['required', 'boolean'],
            'max_branches' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_employees' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['integer', 'distinct', 'exists:modules,id'],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $plan = DB::transaction(function () use ($data, $request) {
            $plan = Plan::create([
                'name' => $data['name'], 'slug' => Str::lower($data['slug']),
                'description' => $data['description'] ?? null,
                'monthly_price_minor' => (int) round(((float) $data['monthly_price']) * 100),
                'currency' => $data['currency'],
                'max_branches' => $data['max_branches'] ?? null,
                'max_employees' => $data['max_employees'] ?? null,
                'is_active' => $data['is_active'], 'is_public' => $data['is_public'],
            ]);
            $plan->modules()->sync($data['modules'] ?? []);
            AuditLog::create([
                'actor_id' => $request->user()->id, 'action' => 'plan.created',
                'subject_type' => Plan::class, 'subject_id' => $plan->id,
                'ip_address' => $request->ip(),
            ]);
            return $plan;
        });

        return redirect()->route('platform.plans.edit', $plan)->with('status', 'Plan created.');
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        $data = $this->validated($request, $plan);
        DB::transaction(function () use ($request, $plan, $data) {
            $plan->update([
                'name' => $data['name'], 'slug' => Str::lower($data['slug']),
                'description' => $data['description'] ?? null,
                'monthly_price_minor' => (int) round(((float) $data['monthly_price']) * 100),
                'currency' => $data['currency'],
                'max_branches' => $data['max_branches'] ?? null,
                'max_employees' => $data['max_employees'] ?? null,
                'is_active' => $data['is_active'], 'is_public' => $data['is_public'],
            ]);
            $plan->modules()->sync($data['modules'] ?? []);
            AuditLog::create([
                'actor_id' => $request->user()->id, 'action' => 'plan.updated',
                'subject_type' => Plan::class, 'subject_id' => $plan->id,
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Plan and module inclusions saved.');
    }
}
