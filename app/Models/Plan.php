<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    protected $fillable = ['slug', 'name', 'description', 'currency', 'monthly_price_minor', 'yearly_price_minor', 'is_public', 'is_active', 'is_discount_available', 'discount_percentage', 'max_branches', 'max_employees'];

    protected function casts(): array
    {
        return [
            'is_public' => 'boolean',
            'is_active' => 'boolean',
            'is_discount_available' => 'boolean',
            'discount_percentage' => 'decimal:2',
            'monthly_price_minor' => 'integer',
            'yearly_price_minor' => 'integer',
            'max_branches' => 'integer', 'max_employees' => 'integer',
        ];
    }

    /**
     * Return the discounted charge in integer minor units so presentation
     * and checkout use the exact same amount. Round percentage savings once.
     */
    public function priceForCycleMinor(string $cycle): ?int
    {
        $price = match ($cycle) {
            'monthly' => $this->monthly_price_minor,
            'yearly' => $this->yearly_price_minor,
            default => null,
        };
        if ($price === null) {
            return null;
        }
        if (! $this->is_discount_available || $this->discount_percentage === null) {
            return (int) $price;
        }

        $basisPoints = (int) round(((float) $this->discount_percentage) * 100);
        $discount = intdiv(((int) $price * $basisPoints) + 5000, 10000);
        return max(0, (int) $price - $discount);
    }

    public function discountIsActive(): bool
    {
        return $this->is_discount_available && $this->discount_percentage !== null
            && (float) $this->discount_percentage > 0;
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function modules(): BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'plan_modules');
    }
}
