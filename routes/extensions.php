<?php

use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\ProductWorkbenchController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Storefront\BannerInteractionController;
use App\Http\Controllers\Storefront\CatalogController;
use Illuminate\Support\Facades\Route;

Route::get('/brand/{brand}', [CatalogController::class, 'brand'])->name('brand.show');
Route::post('/banners/{banner}/impression', [BannerInteractionController::class, 'impression'])
    ->middleware('throttle:120,1')
    ->name('banners.impression');
Route::get('/banners/{banner}/click', [BannerInteractionController::class, 'click'])
    ->middleware('throttle:60,1')
    ->name('banners.click');

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

    Route::prefix('products/{product}/workbench')->name('products.workbench.')->middleware('permission:catalog.manage')->group(function (): void {
        Route::get('/', [ProductWorkbenchController::class, 'show'])->name('show');
        Route::post('/media', [ProductWorkbenchController::class, 'storeMedia'])->name('media.store');
        Route::put('/media', [ProductWorkbenchController::class, 'updateMedia'])->name('media.update');
        Route::delete('/media/{media}', [ProductWorkbenchController::class, 'destroyMedia'])->name('media.destroy');
        Route::post('/variants', [ProductWorkbenchController::class, 'storeVariant'])->name('variants.store');
        Route::put('/variants/{variant}', [ProductWorkbenchController::class, 'updateVariant'])->name('variants.update');
        Route::delete('/variants/{variant}', [ProductWorkbenchController::class, 'destroyVariant'])->name('variants.destroy');
        Route::put('/specifications', [ProductWorkbenchController::class, 'syncSpecifications'])->name('specifications');
        Route::put('/relations', [ProductWorkbenchController::class, 'syncRelations'])->name('relations');
    });

    Route::get('/banners', [AdminBannerController::class, 'index'])->middleware('permission:content.view')->name('banners.index');
    Route::post('/banners', [AdminBannerController::class, 'store'])->middleware('permission:content.manage')->name('banners.store');
    Route::put('/banners/{banner}', [AdminBannerController::class, 'update'])->middleware('permission:content.manage')->name('banners.update');
    Route::patch('/banners/{banner}/toggle', [AdminBannerController::class, 'toggle'])->middleware('permission:content.manage')->name('banners.toggle');
    Route::delete('/banners/{banner}', [AdminBannerController::class, 'destroy'])->middleware('permission:content.manage')->name('banners.destroy');
});
