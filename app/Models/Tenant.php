<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    protected $fillable = [
        'name', 'slug', 'contact_email', 'phone', 'currency', 'timezone',
        'address', 'status', 'created_by', 'onboarding_completed_at',
    ];

    protected function casts(): array
    {
        return ['onboarding_completed_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'tenant_user')
            ->withPivot(['is_owner', 'status'])->withTimestamps();
    }

    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function trial(): HasOne
    {
        return $this->hasOne(Trial::class);
    }

    public function subscription(): HasOne
    {
        return $this->hasOne(Subscription::class);
    }

    public function paymentRequests(): HasMany
    {
        return $this->hasMany(SubscriptionPaymentRequest::class);
    }

    public function moduleGrants(): HasMany
    {
        return $this->hasMany(TenantModuleGrant::class);
    }

    public function getEffectiveStatusAttribute(): string
    {
        return $this->status === 'trial' && $this->trial?->ends_at->isPast()
            ? 'expired'
            : $this->status;
    }
}
