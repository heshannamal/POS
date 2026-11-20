<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TrialEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'trial_id', 'actor_id', 'action', 'previous_ends_at', 'new_ends_at', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'previous_ends_at' => 'datetime',
            'new_ends_at' => 'datetime',
            'created_at' => 'datetime',
        ];
    }
}
