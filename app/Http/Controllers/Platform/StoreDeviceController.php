<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\StoreDevice;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class StoreDeviceController extends Controller
{
    public function store(Request $request, Branch $store): RedirectResponse
    {
        Tenant::findOrFail($store->tenant_id);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', Rule::in(['pos_terminal', 'receipt_printer', 'barcode_scanner'])],
            'manufacturer' => ['nullable', 'string', 'max:100'],
            'model' => ['nullable', 'string', 'max:120'],
            'serial_number' => ['nullable', 'string', 'max:120',
                Rule::unique('store_devices', 'serial_number')->where('tenant_id', $store->tenant_id)],
        ]);

        DB::transaction(function () use ($store, $data, $request): void {
            Tenant::whereKey($store->tenant_id)->lockForUpdate()->firstOrFail();
            $device = StoreDevice::create([
                ...$data, 'tenant_id' => $store->tenant_id,
                'branch_id' => $store->id, 'status' => 'registered',
                'created_by' => $request->user()->id,
            ]);
            AuditLog::create([
                'tenant_id' => $store->tenant_id, 'actor_id' => $request->user()->id,
                'action' => 'store.device.registered',
                'subject_type' => StoreDevice::class, 'subject_id' => $device->id,
                'metadata' => ['branch_id' => $store->id, 'type' => $device->type],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Device registered for inventory. Connectivity has not been established.');
    }

    public function update(Request $request, Branch $store, StoreDevice $device): RedirectResponse
    {
        Tenant::findOrFail($store->tenant_id);
        abort_unless($device->tenant_id === $store->tenant_id && $device->branch_id === $store->id, 404);

        $data = $request->validate([
            'status' => ['required', Rule::in(['registered', 'inactive', 'retired'])],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        DB::transaction(function () use ($store, $device, $data, $request): void {
            Tenant::whereKey($store->tenant_id)->lockForUpdate()->firstOrFail();
            $locked = StoreDevice::whereKey($device->id)->lockForUpdate()->firstOrFail();
            $before = $locked->status;
            $locked->update(['status' => $data['status']]);
            AuditLog::create([
                'tenant_id' => $store->tenant_id, 'actor_id' => $request->user()->id,
                'action' => 'store.device.status_changed',
                'subject_type' => StoreDevice::class, 'subject_id' => $locked->id,
                'metadata' => [
                    'branch_id' => $store->id, 'before' => $before,
                    'after' => $locked->status, 'reason' => $data['reason'],
                ],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Device inventory status updated.');
    }
}
