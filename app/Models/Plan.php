<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'currency', 'monthly_price_minor', 'yearly_price_minor', 'is_public', 'is_active', 'max_branches', 'max_employees'];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'monthly_price_minor' => 'integer',
            'yearly_price_minor' => 'integer',
            'max_branches' => 'integer', 'max_employees' => 'integer',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'plan_modules');
    }
}
