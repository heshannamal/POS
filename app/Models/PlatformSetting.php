<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformSetting extends Model
{
    protected $primaryKey = 'key';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = ['key', 'value'];

    public static function trialDays(): int
    {
        return max(1, min(90, (int) (static::query()->whereKey('trial.default_days')->value('value') ?? config('saas.default_trial_days', 14))));
    }
}
