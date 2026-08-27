<?php

namespace App\Domain\Order\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['snapshot' => 'array']; }
    /** Returns the owning order. */ public function order(): BelongsTo { return $this->belongsTo(Order::class); }
}
