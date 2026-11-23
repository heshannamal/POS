<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingInvoice extends Model
{
    protected $fillable = ['number','tenant_id','payment_request_id','plan_id','plan_name',
        'billing_cycle','status','currency','list_price_minor','plan_discount_minor',
        'coupon_discount_minor','subtotal_minor','tax_basis_points','tax_minor',
        'total_minor','coupon_code','bill_to_name','bill_to_address','issued_at',
        'due_at','paid_at','voided_at','receipt_number','period_start','period_end'];

    protected function casts(): array
    {
        return [
            'list_price_minor'=>'integer', 'plan_discount_minor'=>'integer',
            'coupon_discount_minor'=>'integer','subtotal_minor'=>'integer',
            'tax_basis_points'=>'integer','tax_minor'=>'integer','total_minor'=>'integer',
            'issued_at'=>'datetime','due_at'=>'datetime','paid_at'=>'datetime',
            'voided_at'=>'datetime','period_start'=>'datetime','period_end'=>'datetime',
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

    public function payment(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPaymentRequest::class,'payment_request_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(BillingRefund::class,'invoice_id');
    }

    public function getRefundedMinorAttribute(): int
    {
        return (int) ($this->refunds_sum_amount_minor ?? $this->refunds()->sum('amount_minor'));
    }

    public function getBalanceMinorAttribute(): int
    {
        return $this->status === 'paid' ? 0 : ($this->status === 'void' ? 0 : $this->total_minor);
    }
}
