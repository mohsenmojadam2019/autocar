<?php

namespace App\Domain\Cart\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Cart extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['expires_at' => 'datetime', 'last_activity_at' => 'datetime']; }
    /** Returns line items currently stored in the cart. */
    public function items(): HasMany { return $this->hasMany(CartItem::class); }
    /** Calculates the current raw subtotal from stored unit-price snapshots. */
    public function subtotal(): int { return (int) $this->items->sum(fn (CartItem $item) => $item->unit_price * $item->quantity); }
}
