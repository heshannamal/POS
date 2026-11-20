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
            'yearly_price' => ['nullable', 'numeric', 'min:0', 'max:10000000', 'decimal:0,2'],
            'currency' => ['required', Rule::in(['LKR', 'USD'])],
            'is_discount_available' => ['required', 'boolean'],
            'discount_percentage' => ['nullable', 'required_if:is_discount_available,1', 'numeric', 'min:0.01', 'max:99.99', 'decimal:0,2'],
            'is_active' => ['required', 'boolean'],
            'is_public' => ['required', 'boolean'],
            'max_branches' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'max_employees' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['integer', 'distinct', 'exists:modules,id'],
        ]);
    }

    private function toMinorUnits(string $amount): int
    {
        [$major, $fraction] = array_pad(explode('.', $amount, 2), 2, '');
        return (int) $major * 100 + (int) str_pad($fraction, 2, '0');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $plan = DB::transaction(function () use ($data, $request) {
            $plan = Plan::create([
                'name' => $data['name'], 'slug' => Str::lower($data['slug']),
                'description' => $data['description'] ?? null,
                'monthly_price_minor' => $this->toMinorUnits((string) $data['monthly_price']),
                'yearly_price_minor' => isset($data['yearly_price']) ? $this->toMinorUnits((string) $data['yearly_price']) : null,
                'currency' => $data['currency'],
                'is_discount_available' => (bool) $data['is_discount_available'],
                'discount_percentage' => $data['is_discount_available'] ? $data['discount_percentage'] : null,
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
                'monthly_price_minor' => $this->toMinorUnits((string) $data['monthly_price']),
                'yearly_price_minor' => isset($data['yearly_price']) ? $this->toMinorUnits((string) $data['yearly_price']) : null,
                'currency' => $data['currency'],
                'is_discount_available' => (bool) $data['is_discount_available'],
                'discount_percentage' => $data['is_discount_available'] ? $data['discount_percentage'] : null,
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
