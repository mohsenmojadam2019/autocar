<?php

namespace App\Domain\Vehicle\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VehicleTrim extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** Returns the generation containing this exact model-year trim. */
    public function generation(): BelongsTo
    {
        return $this->belongsTo(VehicleGeneration::class, 'vehicle_generation_id');
    }

    /** Returns the engine specification attached to the trim, when known. */
    public function engine(): BelongsTo
    {
        return $this->belongsTo(VehicleEngine::class, 'vehicle_engine_id');
    }
}
