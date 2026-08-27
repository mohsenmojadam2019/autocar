<?php

namespace App\Domain\Shipping\Models;

use Illuminate\Database\Eloquent\Model;

class ShippingMethod extends Model
{
    protected $guarded=[]; protected function casts(): array { return ['zones'=>'array','is_active'=>'boolean']; }
    /** Calculates a deterministic shipping quote from cart subtotal and shipment weight. */ public function quote(int $subtotal,int $weightGrams): int { if($this->free_over && $subtotal >= $this->free_over) return 0; return (int)$this->base_price + (int)ceil(max(0,$weightGrams)/1000)*(int)$this->price_per_kg; }
}
