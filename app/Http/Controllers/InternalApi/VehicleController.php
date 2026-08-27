<?php

namespace App\Http\Controllers\InternalApi;

use App\Domain\Vehicle\Models\VehicleGeneration;
use App\Domain\Vehicle\Models\VehicleMake;
use App\Domain\Vehicle\Models\VehicleModel;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class VehicleController extends Controller
{
    /** Lists active vehicle makes for the storefront selector. */
    public function makes(): JsonResponse
    {
        return response()->json(['data' => VehicleMake::query()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'name_en', 'slug'])]);
    }

    /** Lists models belonging to one make. */
    public function models(VehicleMake $make): JsonResponse
    {
        return response()->json(['data' => $make->models()->where('is_active', true)->orderBy('name')->get(['id', 'name', 'name_en', 'slug'])]);
    }

    /** Lists generations belonging to one model. */
    public function generations(VehicleModel $model): JsonResponse
    {
        return response()->json(['data' => $model->generations()->orderByDesc('from_year')->get(['id', 'name', 'from_year', 'to_year', 'body_type'])]);
    }

    /** Lists exact trim/year configurations used by fitment resolver. */
    public function trims(VehicleGeneration $generation): JsonResponse
    {
        return response()->json(['data' => $generation->trims()->where('is_active', true)->with('engine:id,name,code,displacement_cc')->orderByDesc('year')->get()]);
    }
}
