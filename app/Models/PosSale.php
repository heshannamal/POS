<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosSale extends Model
{
    protected $fillable = ['tenant_id','branch_id','shift_id','cashier_id','idempotency_key','status','subtotal_minor','discount_minor','total_minor','currency','completed_at'];

    protected function casts(): array
    {
        return ['subtotal_minor'=>'integer','discount_minor'=>'integer','total_minor'=>'integer','completed_at'=>'datetime'];
    }

    public function items() { return $this->hasMany(PosSaleItem::class, 'sale_id'); }
    public function payments() { return $this->hasMany(PosSalePayment::class, 'sale_id'); }
}