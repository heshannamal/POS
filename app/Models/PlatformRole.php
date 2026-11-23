<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformRole extends Model
{
    protected $fillable = ['name', 'slug', 'description', 'scopes', 'permissions', 'is_active'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'permissions' => 'array', 'is_active' => 'boolean'];
    }
}
