<?php

namespace App\Domain\Payment\Models;

use App\Domain\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentTransaction extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['request_payload' => 'array', 'callback_payload' => 'array', 'verify_payload' => 'array', 'verified_at' => 'datetime'];
    }

    /** Returns the order paid by this transaction. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
