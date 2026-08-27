<?php

namespace App\Domain\Shipping\Models;

use App\Domain\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shipment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['label_data' => 'array', 'shipped_at' => 'datetime', 'delivered_at' => 'datetime'];
    }

    /** Returns the order delivered by this shipment. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
