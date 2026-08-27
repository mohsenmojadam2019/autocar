<?php

use App\Http\Controllers\Admin\PromotionController;
use Illuminate\Support\Facades\Route;

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function (): void {
    Route::post('/promotions/automatic', [PromotionController::class, 'storeAutomatic'])
        ->middleware('permission:marketing.manage')
        ->name('promotions.automatic.store');
    Route::patch('/promotions/automatic/{promotion}/toggle', [PromotionController::class, 'toggleAutomatic'])
        ->middleware('permission:marketing.manage')
        ->name('promotions.automatic.toggle');
    Route::delete('/promotions/automatic/{promotion}', [PromotionController::class, 'destroyAutomatic'])
        ->middleware('permission:marketing.manage')
        ->name('promotions.automatic.destroy');
});
