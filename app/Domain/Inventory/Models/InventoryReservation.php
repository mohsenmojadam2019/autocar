<?php

namespace App\Domain\Inventory\Models;

use App\Domain\Order\Models\Order;
use App\Domain\Order\Models\OrderItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryReservation extends Model
{
    protected $guarded = [];

    /** Casts reservation lifecycle timestamps used by expiry and fulfilment workers. */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'committed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    /** Returns the order holding this reservation. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Returns the immutable order line backed by this reservation. */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** Returns the warehouse stock row currently reserved. */
    public function stockItem(): BelongsTo
    {
        return $this->belongsTo(StockItem::class);
    }
}
