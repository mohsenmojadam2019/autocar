<?php

namespace App\Domain\Promotion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Coupon extends Model
{
    protected $guarded = [];

    /** Casts coupon flags, windows and rule metadata into application-safe values. */
    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'first_order_only' => 'boolean',
            'stackable' => 'boolean',
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'value' => 'decimal:2',
        ];
    }

    /** Returns immutable coupon redemption records used for usage-limit enforcement and reporting. */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
