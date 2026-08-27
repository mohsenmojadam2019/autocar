<?php

namespace App\Domain\Vehicle\Models;

use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Vehicle\Enums\FitmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFitment extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['status' => FitmentStatus::class, 'is_exclusion' => 'boolean'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function make(): BelongsTo
    {
        return $this->belongsTo(VehicleMake::class, 'vehicle_make_id');
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(VehicleModel::class, 'vehicle_model_id');
    }

    public function generation(): BelongsTo
    {
        return $this->belongsTo(VehicleGeneration::class, 'vehicle_generation_id');
    }

    public function trim(): BelongsTo
    {
        return $this->belongsTo(VehicleTrim::class, 'vehicle_trim_id');
    }

    public function engine(): BelongsTo
    {
        return $this->belongsTo(VehicleEngine::class, 'vehicle_engine_id');
    }
}
