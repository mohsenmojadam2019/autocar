<?php

use App\Http\Controllers\InternalApi\CommerceController;
use App\Http\Controllers\InternalApi\SearchController;
use App\Http\Controllers\InternalApi\VehicleController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:120,1')->group(function (): void {
    Route::get('/search/suggest', [SearchController::class, 'suggest']);
    Route::get('/products/{product}', [CommerceController::class, 'product']);
    Route::get('/categories/{category}/products', [CommerceController::class, 'category']);
    Route::get('/vehicles/makes', [VehicleController::class, 'makes']);
    Route::get('/vehicles/makes/{make}/models', [VehicleController::class, 'models']);
    Route::get('/vehicles/models/{model}/generations', [VehicleController::class, 'generations']);
    Route::get('/vehicles/generations/{generation}/trims', [VehicleController::class, 'trims']);
    Route::get('/fitment/{product}/{trim}', [CommerceController::class, 'fitment'])->middleware('throttle:180,1');

    Route::middleware('web')->group(function (): void {
        Route::get('/cart', [CommerceController::class, 'cart']);
        Route::post('/cart/{product}', [CommerceController::class, 'addCart']);
        Route::put('/cart/{product}', [CommerceController::class, 'updateCart']);
        Route::delete('/cart/{product}', [CommerceController::class, 'removeCart']);

        Route::middleware('auth')->group(function (): void {
            Route::get('/account', [CommerceController::class, 'account']);
            Route::get('/account/orders', [CommerceController::class, 'orders']);
        });
    });
});
