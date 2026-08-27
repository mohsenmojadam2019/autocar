<?php

namespace App\Domain\Promotion\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CouponRedemption extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    /** Returns the coupon consumed by this immutable redemption record. */
    public function coupon(): BelongsTo
    {
        return $this->belongsTo(Coupon::class);
    }
}
