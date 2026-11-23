<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SecurityAlert extends Model
{
    protected $fillable = [
        'user_id', 'type', 'severity', 'ip_address',
        'metadata', 'resolved_at', 'resolved_by',
    ];

    protected function casts(): array
    {
        return ['metadata' => 'array', 'resolved_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
