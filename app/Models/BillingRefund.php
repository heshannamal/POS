<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingRefund extends Model
{
    protected $fillable = ['invoice_id','tenant_id','recorded_by','credit_note_number',
        'external_reference','amount_minor','currency','method','reason','verified_at'];

    protected function casts(): array
    {
        return ['amount_minor'=>'integer','verified_at'=>'datetime'];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(BillingInvoice::class,'invoice_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
