<?php

namespace App\Domain\Returns\Models;

use App\Domain\Order\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReturnRequest extends Model
{
    protected $table = 'returns';

    protected $guarded = [];

    /** Returns the original order. */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /** Returns the customer who requested this RMA. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Returns requested line items. */
    public function items(): HasMany
    {
        return $this->hasMany(ReturnItem::class, 'return_id');
    }
}
