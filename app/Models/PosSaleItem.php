<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosSaleItem extends Model
{
    protected $fillable = ['sale_id','product_id','name','sku','quantity','unit_price_minor','line_total_minor'];
}