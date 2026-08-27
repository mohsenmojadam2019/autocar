<?php

use App\Http\Controllers\InternalApi\SearchController;
use App\Http\Controllers\InternalApi\VehicleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:120,1')->group(function (): void {
    Route::get('/search/suggest',[SearchController::class,'suggest']);
    Route::get('/vehicles/makes',[VehicleController::class,'makes']);
    Route::get('/vehicles/makes/{make}/models',[VehicleController::class,'models']);
    Route::get('/vehicles/models/{model}/generations',[VehicleController::class,'generations']);
    Route::get('/vehicles/generations/{generation}/trims',[VehicleController::class,'trims']);
});
