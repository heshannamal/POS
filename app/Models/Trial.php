<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Trial extends Model
{
    protected $fillable = [
        'tenant_id', 'started_at', 'ends_at', 'duration_days',
        'extension_days', 'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ends_at' => 'datetime',
            'converted_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function getDaysRemainingAttribute(): int
    {
        return max(0, (int) ceil(now()->diffInSeconds($this->ends_at, false) / 86400));
    }
}
