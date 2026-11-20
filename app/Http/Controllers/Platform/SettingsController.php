<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PlatformSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function index(): View
    {
        $settings = [
            'trial_days' => PlatformSetting::trialDays(),
            'registration_enabled' => PlatformSetting::registrationEnabled(),
            'default_currency' => PlatformSetting::defaultCurrency(),
            'default_timezone' => PlatformSetting::defaultTimezone(),
        ];
        $timezones = timezone_identifiers_list();

        return view('platform.settings.index', compact('settings', 'timezones'));
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'trial_days' => ['required', 'integer', 'min:1', 'max:90'],
            'registration_enabled' => ['required', 'boolean'],
            'default_currency' => ['required', Rule::in(['LKR', 'USD'])],
            'default_timezone' => ['required', Rule::in(timezone_identifiers_list())],
        ]);

        DB::transaction(function () use ($data, $request): void {
            foreach ([
                'trial.default_days' => $data['trial_days'],
                'registration.enabled' => $data['registration_enabled'],
                'business.default_currency' => $data['default_currency'],
                'business.default_timezone' => $data['default_timezone'],
            ] as $key => $value) {
                PlatformSetting::updateOrCreate(['key' => $key], ['value' => (string) $value]);
            }
            AuditLog::create([
                'actor_id' => $request->user()->id,
                'action' => 'platform.settings.updated',
                'metadata' => ['keys' => [
                    'trial.default_days', 'registration.enabled',
                    'business.default_currency', 'business.default_timezone',
                ]],
                'ip_address' => $request->ip(),
            ]);
        });

        return back()->with('status', 'Platform settings saved. Changes apply to newly created businesses.');
    }
}
