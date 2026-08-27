<?php

namespace App\Domain\Promotion\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $guarded = [];
    protected function casts(): array { return ['conditions'=>'array','first_order_only'=>'boolean','stackable'=>'boolean','is_active'=>'boolean','starts_at'=>'datetime','ends_at'=>'datetime','value'=>'decimal:2']; }
}
