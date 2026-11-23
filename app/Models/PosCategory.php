<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PosCategory extends Model
{
    protected $fillable = ['tenant_id','name','slug'];
}