<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    public static function valueFor(string $key, string $default = ''): string
    {
        return (string) (static::query()->whereKey($key)->value('value') ?? $default);
    }

    public static function registrationEnabled(): bool
    {
        return static::valueFor('registration.enabled', '1') === '1';
    }

    public static function defaultCurrency(): string
    {
        $currency = static::valueFor('business.default_currency', config('saas.default_currency', 'LKR'));
        return in_array($currency, ['LKR', 'USD'], true) ? $currency : 'LKR';
    }

    public static function defaultTimezone(): string
    {
        $timezone = static::valueFor('business.default_timezone', config('saas.default_timezone', 'Asia/Colombo'));
        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'Asia/Colombo';
    }

    public static function trialDays(): int
    {
        return max(1, min(90, (int) (static::query()->whereKey('trial.default_days')->value('value') ?? config('saas.default_trial_days', 14))));
    }
}
