<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PaymentMethod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PaymentSettingsController extends Controller
{
    public function update(Request $request, PaymentMethod $method): RedirectResponse
    {
        abort_unless(in_array($method->code, ['card', 'asset'], true), 404);

        $data = $request->validate([
            'is_enabled' => ['required', 'boolean'],
            'instructions' => ['nullable', 'string', 'max:3000'],
        ]);
        if ($method->code === 'asset' && (bool) $data['is_enabled'] && mb_strlen(trim((string) ($data['instructions'] ?? ''))) < 10) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'instructions' => 'Add payment instructions before enabling proof uploads.',
            ]);
        }
        DB::transaction(function () use ($method, $data, $request) {
            $before = $method->is_enabled;
            $method->update([
                'is_enabled' => $data['is_enabled'],
                'instructions' => $data['instructions'] ?? null,
                'updated_by' => $request->user()->id,
            ]);
            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'payment.method.updated',
                'subject_type' => PaymentMethod::class,
                'subject_id' => $method->id,
                'metadata' => [
                    'code' => $method->code,
                    'from' => $before, 'to' => (bool) $data['is_enabled'],
                    'operational' => $method->fresh()->is_operational,
                ],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', $method->name.' preferences saved. Card checkout still requires a verified gateway integration.');
    }
}
