<?php

namespace App\Domain\Vehicle\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleMake extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Returns vehicle models belonging to this manufacturer/brand. */
    public function models(): HasMany
    {
        return $this->hasMany(VehicleModel::class)->orderBy('name');
    }

    /** Limits selectors and storefront lists to active makes. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
