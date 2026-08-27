<?php

namespace App\Domain\Vehicle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleGeneration extends Model
{
    protected $guarded = [];

    /** Returns the vehicle model that owns this production generation. */
    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
    }
}
