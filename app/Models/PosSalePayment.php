<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosSalePayment extends Model
{
    protected $fillable = ['sale_id','method','amount_minor','reference'];
}