<?php

namespace App\Domain\Invoice\Models;

use App\Domain\Order\Models\Order;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'is_official' => 'boolean', 'issued_at' => 'datetime'];
    }

    /** Returns the order snapshot represented by this invoice. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
