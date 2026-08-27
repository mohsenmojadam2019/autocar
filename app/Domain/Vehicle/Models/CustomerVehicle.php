<?php

namespace App\Domain\Vehicle\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerVehicle extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['is_default' => 'boolean'];
    }

    /** Returns the customer who saved this vehicle in their garage. */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Returns the exact trim/year configuration used for compatibility checks. */
    public function trim(): BelongsTo
    {
        return $this->belongsTo(VehicleTrim::class, 'vehicle_trim_id');
    }
}
