<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BillingCoupon extends Model
{
    protected $fillable = ['code','description','discount_type','discount_value','currency',
        'max_uses','max_uses_per_tenant','starts_at','expires_at','is_active','created_by'];

    protected function casts(): array
    {
        return ['discount_value'=>'integer','max_uses'=>'integer',
            'max_uses_per_tenant'=>'integer','is_active'=>'boolean',
            'starts_at'=>'datetime','expires_at'=>'datetime'];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(BillingCouponRedemption::class,'coupon_id');
    }
}
