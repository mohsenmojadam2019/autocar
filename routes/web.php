<?php

use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordRecoveryController;
use App\Http\Controllers\Customer\AccountController;
use App\Http\Controllers\Customer\DashboardController as CustomerDashboardController;
use App\Http\Controllers\Customer\GarageController;
use App\Http\Controllers\Storefront\CartController;
use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\CompareController;
use App\Http\Controllers\Storefront\ContentController;
use App\Http\Controllers\Storefront\FeedbackController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\PartRequestController;
use App\Http\Controllers\Storefront\TrackingController;
use App\Http\Controllers\Storefront\WishlistController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/search', [CatalogController::class, 'search'])->name('search');
Route::get('/category/{category}', [CatalogController::class, 'category'])->name('category.show');
Route::get('/product/{product}', [CatalogController::class, 'product'])->name('product.show');
Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/{product}', [CartController::class, 'add'])->name('cart.add');
Route::patch('/cart/items/{item}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/items/{item}', [CartController::class, 'remove'])->name('cart.remove');
Route::get('/compare', [CompareController::class, 'index'])->name('compare.index');
Route::post('/compare/{product}', [CompareController::class, 'store'])->name('compare.store');
Route::delete('/compare/{product}', [CompareController::class, 'destroy'])->name('compare.destroy');
Route::get('/track-order', TrackingController::class)->name('tracking');
Route::get('/request-part', [PartRequestController::class, 'create'])->name('part-request.create');
Route::post('/request-part', [PartRequestController::class, 'store'])->middleware('throttle:10,1')->name('part-request.store');
Route::get('/page/{slug}', [ContentController::class, 'page'])->name('content.page');
Route::get('/blog', [ContentController::class, 'blog'])->name('blog.index');
Route::get('/blog/{slug}', [ContentController::class, 'post'])->name('blog.show');
Route::get('/faq', [ContentController::class, 'faq'])->name('faq');
Route::post('/product/{product}/questions', [FeedbackController::class, 'question'])->middleware('throttle:10,1')->name('product.question');
Route::post('/reviews/{review}/report', [FeedbackController::class, 'reportReview'])->middleware('throttle:10,1')->name('review.report');

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login/password', [LoginController::class, 'password'])->name('login.password');
    Route::post('/login/otp/request', [LoginController::class, 'requestOtp'])->name('login.otp.request');
    Route::post('/login/otp/verify', [LoginController::class, 'verifyOtp'])->name('login.otp.verify');
    Route::get('/forgot-password', [PasswordRecoveryController::class, 'create'])->name('password.forgot');
    Route::post('/forgot-password/otp', [PasswordRecoveryController::class, 'requestOtp'])->middleware('throttle:5,1')->name('password.otp');
    Route::post('/forgot-password/reset', [PasswordRecoveryController::class, 'reset'])->name('password.reset');
});
Route::post('/logout', [LoginController::class, 'destroy'])->middleware('auth')->name('logout');
Route::get('/payment/{gateway}/callback', [CheckoutController::class, 'callback'])->name('payment.callback');
Route::get('/payment/result/{order}', [CheckoutController::class, 'result'])->name('payment.result');

Route::middleware('auth')->group(function (): void {
    Route::get('/checkout', [CheckoutController::class, 'index'])->name('checkout.index');
    Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');
    Route::post('/product/{product}/reviews', [FeedbackController::class, 'review'])->middleware('throttle:5,1')->name('product.review');
    Route::get('/wishlist', [WishlistController::class, 'index'])->name('wishlist.index');
    Route::post('/wishlist/{product}', [WishlistController::class, 'store'])->name('wishlist.store');
    Route::delete('/wishlist/{product}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

    Route::prefix('account')->name('account.')->group(function (): void {
        Route::get('/', CustomerDashboardController::class)->name('dashboard');
        Route::get('/garage', [GarageController::class, 'index'])->name('garage');
        Route::post('/garage', [GarageController::class, 'store'])->name('garage.store');
        Route::delete('/garage/{vehicle}', [GarageController::class, 'destroy'])->name('garage.destroy');
        Route::get('/orders', [AccountController::class, 'orders'])->name('orders');
        Route::get('/orders/{number}', [AccountController::class, 'order'])->name('orders.show');
        Route::get('/orders/{number}/invoice', [AccountController::class, 'invoice'])->name('orders.invoice');
        Route::post('/orders/{number}/return', [AccountController::class, 'requestReturn'])->name('orders.return');
        Route::post('/payments/{transaction}/proof', [AccountController::class, 'submitManualPayment'])->name('payments.proof');
        Route::get('/wallet', [AccountController::class, 'wallet'])->name('wallet');
        Route::get('/addresses', [AccountController::class, 'addresses'])->name('addresses');
        Route::post('/addresses', [AccountController::class, 'storeAddress'])->name('addresses.store');
        Route::delete('/addresses/{address}', [AccountController::class, 'destroyAddress'])->name('addresses.destroy');
        Route::get('/notifications', [AccountController::class, 'notifications'])->name('notifications');
        Route::put('/notifications/preferences', [AccountController::class, 'updateNotificationPreferences'])->name('notifications.preferences');
        Route::get('/returns', [AccountController::class, 'returns'])->name('returns');
        Route::get('/tickets', [AccountController::class, 'tickets'])->name('tickets');
        Route::post('/tickets', [AccountController::class, 'storeTicket'])->name('tickets.store');
        Route::get('/tickets/{number}', [AccountController::class, 'ticket'])->name('tickets.show');
        Route::post('/tickets/{number}/reply', [AccountController::class, 'replyTicket'])->name('tickets.reply');
    });
});

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function (): void {
    Route::get('/', AdminDashboardController::class)->middleware('permission:reports.view')->name('dashboard');
    Route::resource('products', AdminProductController::class)->except(['show', 'destroy'])->middleware('permission:catalog.manage');
    Route::post('/products/{product}/duplicate', [AdminProductController::class, 'duplicate'])->middleware('permission:catalog.manage')->name('products.duplicate');
    Route::get('/categories', [AdminCategoryController::class, 'index'])->middleware('permission:catalog.manage')->name('categories.index');
    Route::post('/categories', [AdminCategoryController::class, 'store'])->middleware('permission:catalog.manage')->name('categories.store');
    Route::patch('/categories/{category}/move', [AdminCategoryController::class, 'move'])->middleware('permission:catalog.manage')->name('categories.move');
    Route::get('/orders', [AdminOrderController::class, 'index'])->middleware('permission:orders.view')->name('orders.index');
    Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->middleware('permission:orders.view')->name('orders.show');
    Route::patch('/orders/{order}/status', [AdminOrderController::class, 'transition'])->middleware('permission:orders.manage')->name('orders.transition');
    Route::get('/settings', [AdminSettingsController::class, 'index'])->middleware('permission:settings.view')->name('settings.index');
    Route::put('/settings', [AdminSettingsController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');
});
