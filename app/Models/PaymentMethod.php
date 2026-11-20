<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentMethod extends Model
{
    protected $fillable = ['name', 'is_enabled', 'instructions', 'updated_by'];

    protected function casts(): array
    {
        return ['is_enabled' => 'boolean'];
    }

    public function getIsOperationalAttribute(): bool
    {
        // No verified card acquirer is connected yet. A toggle cannot make an
        // unverified checkout endpoint safe or functional.
        return $this->is_enabled && $this->code === 'asset';
    }
}
