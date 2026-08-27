<?php

namespace App\Domain\Inventory\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Warehouse extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Returns stock rows managed by this warehouse. */
    public function stockItems(): HasMany
    {
        return $this->hasMany(StockItem::class);
    }
}
