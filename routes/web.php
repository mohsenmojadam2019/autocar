<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Customer\GarageController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\HomeController;
use Illuminate\Support\Facades\Route;

Route::get('/',HomeController::class)->name('home');
Route::get('/search',[CatalogController::class,'search'])->name('search');
Route::get('/category/{slug}',[CatalogController::class,'category'])->name('category.show');
Route::get('/product/{slug}',[CatalogController::class,'product'])->name('product.show');
Route::get('/cart',[CartController::class,'index'])->name('cart.index');
Route::post('/cart/{product}',[CartController::class,'add'])->name('cart.add');
Route::patch('/cart/items/{item}',[CartController::class,'update'])->name('cart.update');
Route::delete('/cart/items/{item}',[CartController::class,'remove'])->name('cart.remove');
Route::get('/login',[LoginController::class,'create'])->middleware('guest')->name('login');
Route::post('/login/password',[LoginController::class,'password'])->middleware('guest')->name('login.password');
Route::post('/login/otp/request',[LoginController::class,'requestOtp'])->middleware('guest')->name('login.otp.request');
Route::post('/login/otp/verify',[LoginController::class,'verifyOtp'])->middleware('guest')->name('login.otp.verify');
Route::post('/logout',[LoginController::class,'destroy'])->middleware('auth')->name('logout');
Route::get('/payment/{gateway}/callback',[CheckoutController::class,'callback'])->name('payment.callback');
Route::get('/payment/result/{order}',[CheckoutController::class,'result'])->name('payment.result');

Route::middleware('auth')->group(function(): void {
    Route::get('/checkout',[CheckoutController::class,'index'])->name('checkout.index');
    Route::post('/checkout',[CheckoutController::class,'store'])->name('checkout.store');
    Route::get('/account',CustomerDashboardController::class)->name('account.dashboard');
    Route::get('/account/garage',[GarageController::class,'index'])->name('account.garage');
    Route::post('/account/garage',[GarageController::class,'store'])->name('account.garage.store');
    Route::delete('/account/garage/{vehicle}',[GarageController::class,'destroy'])->name('account.garage.destroy');
});

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function(): void {
    Route::get('/',AdminDashboardController::class)->middleware('permission:reports.view')->name('dashboard');
    Route::resource('products',AdminProductController::class)->except(['show','destroy'])->middleware('permission:catalog.manage');
    Route::post('/products/{product}/duplicate',[AdminProductController::class,'duplicate'])->middleware('permission:catalog.manage')->name('products.duplicate');
    Route::get('/categories',[AdminCategoryController::class,'index'])->middleware('permission:catalog.manage')->name('categories.index');
    Route::post('/categories',[AdminCategoryController::class,'store'])->middleware('permission:catalog.manage')->name('categories.store');
    Route::patch('/categories/{category}/move',[AdminCategoryController::class,'move'])->middleware('permission:catalog.manage')->name('categories.move');
    Route::get('/orders',[AdminOrderController::class,'index'])->middleware('permission:orders.view')->name('orders.index');
    Route::get('/orders/{order}',[AdminOrderController::class,'show'])->middleware('permission:orders.view')->name('orders.show');
    Route::patch('/orders/{order}/status',[AdminOrderController::class,'transition'])->middleware('permission:orders.manage')->name('orders.transition');
    Route::get('/settings',[AdminSettingsController::class,'index'])->middleware('permission:settings.view')->name('settings.index');
    Route::put('/settings',[AdminSettingsController::class,'update'])->middleware('permission:settings.manage')->name('settings.update');
});
