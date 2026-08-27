<?php

namespace App\Domain\Order\Models;

use App\Domain\Order\Enums\OrderStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['status'=>OrderStatus::class,'billing_address'=>'array','shipping_address'=>'array','paid_at'=>'datetime','completed_at'=>'datetime','cancelled_at'=>'datetime']; }
    /** Returns immutable line-item snapshots. */ public function items(): HasMany { return $this->hasMany(OrderItem::class); }
    /** Returns the full lifecycle timeline. */ public function statusHistory(): HasMany { return $this->hasMany(OrderStatusHistory::class); }
}
