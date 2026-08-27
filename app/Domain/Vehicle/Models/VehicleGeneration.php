<?php

namespace App\Domain\Vehicle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VehicleGeneration extends Model
{
    protected $guarded = [];

    /** Returns the vehicle model that owns this production generation. */
    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
    }

    /** Returns exact year/trim configurations inside this generation. */
    public function trims(): HasMany
    {
        return $this->hasMany(VehicleTrim::class)->orderByDesc('year')->orderBy('name');
    }
}
