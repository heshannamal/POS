<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Tenant extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name', 'slug', 'contact_email', 'phone', 'currency', 'timezone',
        'address', 'country', 'website', 'registration_number', 'logo_path',
        'status', 'lifecycle_previous_status', 'created_by', 'onboarding_completed_at',
        'onboarding_status',
    ];

    protected function casts(): array
    {
        return ['onboarding_completed_at' => 'datetime', 'deleted_at' => 'datetime'];
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

    public function notes(): HasMany
    {
        return $this->hasMany(TenantNote::class);
    }

    public function getOnboardingProgressAttribute(): int
    {
        return match ($this->onboarding_status) {
            'pending' => 25,
            'verified' => 65,
            'completed' => 100,
            default => 0,
        };
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
        if ($this->trashed()) {
            return 'archived';
        }
        if ($this->status === 'trial') {
            if ($this->trial && ! $this->trial->is_active) {
                return 'inactive';
            }
            return $this->trial?->ends_at?->isPast() ? 'expired' : 'trial';
        }

        return $this->status;
    }
}
