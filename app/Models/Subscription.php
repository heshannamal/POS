<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    protected $fillable = ['tenant_id', 'plan_id', 'status', 'source', 'started_at', 'ends_at', 'auto_renew', 'billing_cycle', 'cancel_at_period_end'];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'ends_at' => 'datetime', 'auto_renew'=>'boolean', 'cancel_at_period_end'=>'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
