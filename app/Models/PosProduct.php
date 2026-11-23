<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PosProduct extends Model
{
    use SoftDeletes;

    protected $fillable = ['tenant_id','category_id','name','sku','barcode','brand','description','cost_minor','price_minor','track_stock','is_active'];

    protected function casts(): array
    {
        return ['cost_minor'=>'integer','price_minor'=>'integer','track_stock'=>'boolean','is_active'=>'boolean'];
    }

    public function stocks()
    {
        return $this->hasMany(PosStock::class, 'product_id');
    }

    public function category()
    {
        return $this->belongsTo(PosCategory::class, 'category_id');
    }
}