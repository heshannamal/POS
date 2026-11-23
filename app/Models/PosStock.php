<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosStock extends Model
{
    protected $fillable = ['tenant_id','branch_id','product_id','quantity','reorder_level'];
    protected function casts(): array { return ['quantity'=>'integer','reorder_level'=>'integer']; }
}