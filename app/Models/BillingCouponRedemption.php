<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingCouponRedemption extends Model
{
    protected $fillable = ['coupon_id','tenant_id','payment_request_id','discount_minor','status'];
}
