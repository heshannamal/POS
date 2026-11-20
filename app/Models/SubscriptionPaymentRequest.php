<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionPaymentRequest extends Model
{
    protected $fillable = [
        'tenant_id', 'plan_id', 'previous_plan_id', 'submitted_by', 'method', 'billing_cycle',
        'requested_amount_minor', 'currency', 'status', 'proof_path',
        'customer_reference', 'provider_reference', 'provider_verified_at',
        'approved_amount_minor', 'review_reference', 'reviewed_by',
        'reviewed_at', 'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'requested_amount_minor' => 'integer',
            'approved_amount_minor' => 'integer',
            'provider_verified_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
