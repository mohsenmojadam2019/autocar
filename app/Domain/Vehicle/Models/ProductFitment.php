<?php

namespace App\Domain\Vehicle\Models;

use App\Domain\Vehicle\Enums\FitmentStatus;
use Illuminate\Database\Eloquent\Model;

class ProductFitment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => FitmentStatus::class,
            'is_exclusion' => 'boolean',
        ];
    }
}
