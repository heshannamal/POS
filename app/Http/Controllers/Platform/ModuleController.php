<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Module;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ModuleController extends Controller
{
    public function index(Request $request): View
    {
        $query = Module::withCount('plans');
        if ($term = trim((string) $request->query('search', ''))) {
            $query->where(fn ($q) => $q->where('name', 'like', '%'.$term.'%')
                ->orWhere('code', 'like', '%'.$term.'%')
                ->orWhere('category', 'like', '%'.$term.'%'));
        }
        $modules = $query->orderBy('category')->orderBy('name')->paginate(15)->withQueryString();

        return view('platform.modules.index', compact('modules'));
    }

    private function fields(Request $request, bool $creating): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:3000'],
            'monthly_price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:10000000'],
        ];
        if ($creating) {
            $rules['code'] = [
                'required', 'string', 'max:80',
                'regex:/^[a-z][a-z0-9]*(?:\.[a-z][a-z0-9]*)+$/',
                Rule::unique('modules', 'code'),
            ];
        }
        return $request->validate($rules);
    }

    private function minorUnits(string $price): int
    {
        [$major, $fraction] = array_pad(explode('.', $price, 2), 2, '');
        return ((int) $major * 100) + (int) str_pad($fraction, 2, '0');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->fields($request, true);
        $module = DB::transaction(function () use ($request, $data): Module {
            $module = Module::create([
                'name' => $data['name'], 'code' => $data['code'],
                'category' => $data['category'],
                'description' => $data['description'] ?? null,
                'monthly_price_minor' => $this->minorUnits((string) $data['monthly_price']),
                'is_available' => false,
            ]);
            AuditLog::create([
                'actor_id' => $request->user()->id, 'action' => 'module.created',
                'subject_type' => Module::class, 'subject_id' => $module->id,
                'metadata' => ['code' => $module->code, 'released' => false],
                'ip_address' => $request->ip(),
            ]);
            return $module;
        });

        return redirect()->route('platform.modules.edit', $module)->with('status', 'Planned module created.');
    }

    public function create(): View
    {
        return view('platform.modules.form', ['module' => null]);
    }

    public function edit(Module $module): View
    {
        $module->load('plans');
        return view('platform.modules.form', compact('module'));
    }

    public function update(Request $request, Module $module): RedirectResponse
    {
        $data = $this->fields($request, false);
        DB::transaction(function () use ($request, $data, $module) {
            $module->update([
                'name' => $data['name'], 'category' => $data['category'],
                'description' => $data['description'] ?? null,
                'monthly_price_minor' => $this->minorUnits((string) $data['monthly_price']),
            ]);
            AuditLog::create([
                'actor_id' => $request->user()->id, 'action' => 'module.updated',
                'subject_type' => Module::class, 'subject_id' => $module->id,
                'metadata' => ['code' => $module->code],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Module catalog details saved.');
    }
}
