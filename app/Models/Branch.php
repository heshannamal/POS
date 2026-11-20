<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Branch extends Model
{
    protected $fillable = [
        'tenant_id', 'name', 'code', 'phone', 'address', 'is_default', 'is_active', 'status', 'manager_user_id', 'timezone', 'settings_overrides',
    ];

    protected function casts(): array
    {
        return ['is_default' => 'boolean', 'is_active' => 'boolean', 'settings_overrides' => 'array'];
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_user_id');
    }

    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'branch_user')->withPivot(['tenant_id', 'assigned_by'])->withTimestamps();
    }

    public function devices(): HasMany
    {
        return $this->hasMany(StoreDevice::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
