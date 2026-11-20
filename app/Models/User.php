<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Notifications\PosPasswordResetNotification;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasFactory, Notifiable;

    protected $fillable = ['name', 'email', 'password'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_platform_admin' => 'boolean',
            'platform_role' => 'string',
            'temporary_admin_expires_at' => 'datetime',
            'last_login_at' => 'datetime',
            'force_password_reset_at' => 'datetime',
            'auth_revoked_at' => 'datetime',
            'auth_session_version' => 'integer',
        ];
    }

    public function platformRole(): ?string
    {
        return $this->is_platform_admin ? ($this->platform_role ?? 'super_admin') : null;
    }

    public function isPermanentPlatformAdmin(): bool
    {
        return $this->is_platform_admin && $this->temporary_admin_expires_at === null;
    }

    public function isTemporaryAdminExpired(): bool
    {
        return $this->temporary_admin_expires_at !== null
            && $this->temporary_admin_expires_at->isPast();
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_user')->withPivot(['tenant_id', 'assigned_by'])->withTimestamps();
    }

    public function loginEvents(): HasMany
    {
        return $this->hasMany(LoginEvent::class);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new PosPasswordResetNotification($token));
    }

    public function tenants(): BelongsToMany
    {
        return $this->belongsToMany(Tenant::class, 'tenant_user')
            ->withPivot(['is_owner', 'status'])->withTimestamps();
    }

    public function isOwnerOf(Tenant $tenant): bool
    {
        return $this->tenants()->whereKey($tenant->id)
            ->wherePivot('is_owner', true)->wherePivot('status', 'active')->exists();
    }
}
