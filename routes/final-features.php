<?php

use App\Http\Controllers\Admin\FinalCommerceController;
use App\Http\Controllers\Storefront\RecoveryShareController;
use Illuminate\Support\Facades\Route;

Route::get('/shared/wishlist/{token}', [RecoveryShareController::class, 'wishlist'])->name('wishlist.shared');
Route::get('/shared/compare/{token}', [RecoveryShareController::class, 'compare'])->name('compare.shared');
Route::get('/cart/recover/{token}', [RecoveryShareController::class, 'recover'])->name('cart.recover');

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function (): void {
    Route::get('/final-commerce', [FinalCommerceController::class, 'index'])->middleware('permission:marketing.view')->name('final-commerce.index');
    Route::post('/final-commerce/bundles', [FinalCommerceController::class, 'bundle'])->middleware('permission:catalog.manage')->name('final-commerce.bundle');
    Route::put('/final-commerce/categories/{category}/template', [FinalCommerceController::class, 'categoryTemplate'])->middleware('permission:catalog.manage')->name('final-commerce.category-template');
    Route::patch('/final-commerce/categories/reorder', [FinalCommerceController::class, 'reorder'])->middleware('permission:catalog.manage')->name('final-commerce.reorder');
    Route::post('/final-commerce/tags', [FinalCommerceController::class, 'tag'])->middleware('permission:customers.manage')->name('final-commerce.tag');
    Route::post('/final-commerce/customers/{customer}/tags', [FinalCommerceController::class, 'assignTag'])->middleware('permission:customers.manage')->name('final-commerce.assign-tag');
    Route::post('/final-commerce/suppressions', [FinalCommerceController::class, 'suppression'])->middleware('permission:marketing.manage')->name('final-commerce.suppression');
});
