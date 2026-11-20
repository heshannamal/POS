<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Module extends Model
{
    protected $fillable = ['code', 'name', 'description', 'category', 'monthly_price_minor', 'is_available'];

    protected function casts(): array
    {
        return ['is_available' => 'boolean', 'monthly_price_minor' => 'integer'];
    }

    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class, 'plan_modules');
    }
}
