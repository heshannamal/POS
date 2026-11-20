<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TenantModuleGrant extends Model
{
    protected $fillable = ['tenant_id', 'module_id', 'enabled', 'source', 'expires_at', 'granted_by'];

    protected function casts(): array
    {
        return ['enabled' => 'boolean', 'expires_at' => 'datetime'];
    }

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
