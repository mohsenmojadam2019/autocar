<?php

namespace App\Domain\Vehicle\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleModel extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Returns the manufacturer/brand that owns this vehicle model. */
    public function make(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class, 'vehicle_make_id');
    }

    /** Returns all production generations defined for this vehicle model. */
    public function generations(): HasMany
    {
        return $this->hasMany(VehicleGeneration::class)->orderByDesc('from_year');
    }

    /** Limits selectors to active vehicle models. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
