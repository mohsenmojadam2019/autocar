<?php

use App\Http\Controllers\Admin\BrandController as AdminBrandController;
use App\Http\Controllers\Admin\CatalogOperationsController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\CustomerController as AdminCustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\InventoryController as AdminInventoryController;
use App\Http\Controllers\Admin\ManualPaymentController;
use App\Http\Controllers\Admin\MarketingController as AdminMarketingController;
use App\Http\Controllers\Admin\MegaMenuController as AdminMegaMenuController;
use App\Http\Controllers\Admin\OperationsController as AdminOperationsController;
use App\Http\Controllers\Admin\OrderController as AdminOrderController;
use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\Admin\PromotionController as AdminPromotionController;
use App\Http\Controllers\Admin\SecurityController as AdminSecurityController;
use App\Http\Controllers\Admin\SettingsController as AdminSettingsController;
use App\Http\Controllers\Admin\SmsController as AdminSmsController;
use App\Http\Controllers\Admin\SupportController as AdminSupportController;
use App\Http\Controllers\Admin\VehicleManagementController as AdminVehicleController;
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

    Route::middleware('permission:catalog.manage')->group(function (): void {
        Route::resource('products', AdminProductController::class)->except(['show', 'destroy']);
        Route::post('/products/{product}/duplicate', [AdminProductController::class, 'duplicate'])->name('products.duplicate');
        Route::get('/categories', [AdminCategoryController::class, 'index'])->name('categories.index');
        Route::post('/categories', [AdminCategoryController::class, 'store'])->name('categories.store');
        Route::patch('/categories/{category}/move', [AdminCategoryController::class, 'move'])->name('categories.move');
        Route::get('/brands', [AdminBrandController::class, 'index'])->name('brands.index');
        Route::post('/brands', [AdminBrandController::class, 'store'])->name('brands.store');
        Route::put('/brands/{brand}', [AdminBrandController::class, 'update'])->name('brands.update');
        Route::delete('/brands/{brand}', [AdminBrandController::class, 'destroy'])->name('brands.destroy');
        Route::get('/catalog/import-export', [CatalogOperationsController::class, 'index'])->name('catalog-operations.index');
        Route::post('/catalog/import', [CatalogOperationsController::class, 'import'])->name('catalog-operations.import');
        Route::get('/catalog/export', [CatalogOperationsController::class, 'export'])->name('catalog-operations.export');
        Route::patch('/catalog/bulk', [CatalogOperationsController::class, 'bulk'])->name('catalog-operations.bulk');
        Route::get('/catalog/import/{import}/errors', [CatalogOperationsController::class, 'errors'])->name('catalog-operations.errors');
        Route::get('/vehicles', [AdminVehicleController::class, 'index'])->name('vehicles.index');
        Route::post('/vehicles/makes', [AdminVehicleController::class, 'storeMake'])->name('vehicles.makes.store');
        Route::post('/vehicles/models', [AdminVehicleController::class, 'storeModel'])->name('vehicles.models.store');
        Route::post('/vehicles/generations', [AdminVehicleController::class, 'storeGeneration'])->name('vehicles.generations.store');
        Route::post('/vehicles/engines', [AdminVehicleController::class, 'storeEngine'])->name('vehicles.engines.store');
        Route::post('/vehicles/trims', [AdminVehicleController::class, 'storeTrim'])->name('vehicles.trims.store');
        Route::post('/vehicles/fitments', [AdminVehicleController::class, 'storeFitment'])->name('vehicles.fitments.store');
        Route::delete('/vehicles/fitments/{fitment}', [AdminVehicleController::class, 'destroyFitment'])->name('vehicles.fitments.destroy');
    });

    Route::middleware('permission:orders.view')->group(function (): void {
        Route::get('/orders', [AdminOrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [AdminOrderController::class, 'show'])->name('orders.show');
        Route::get('/payments', [AdminOperationsController::class, 'payments'])->name('payments.index');
        Route::get('/returns', [AdminOperationsController::class, 'returns'])->name('returns.index');
    });
    Route::patch('/orders/{order}/status', [AdminOrderController::class, 'transition'])->middleware('permission:orders.manage')->name('orders.transition');
    Route::get('/payments/manual', [ManualPaymentController::class, 'index'])->middleware('permission:orders.refund')->name('payments.manual');
    Route::post('/payments/manual/{proof}/approve', [ManualPaymentController::class, 'approve'])->middleware('permission:orders.refund')->name('payments.manual.approve');
    Route::post('/payments/manual/{proof}/reject', [ManualPaymentController::class, 'reject'])->middleware('permission:orders.refund')->name('payments.manual.reject');

    Route::get('/inventory', [AdminInventoryController::class, 'index'])->middleware('permission:inventory.view')->name('inventory.index');
    Route::patch('/inventory/{stockItem}/adjust', [AdminInventoryController::class, 'adjust'])->middleware('permission:inventory.adjust')->name('inventory.adjust');
    Route::post('/inventory/transfer', [AdminInventoryController::class, 'transfer'])->middleware('permission:inventory.manage')->name('inventory.transfer');
    Route::post('/inventory/count', [AdminInventoryController::class, 'count'])->middleware('permission:inventory.adjust')->name('inventory.count');

    Route::get('/customers', [AdminCustomerController::class, 'index'])->middleware('permission:customers.view')->name('customers.index');
    Route::get('/customers/{customer}', [AdminCustomerController::class, 'show'])->middleware('permission:customers.view')->name('customers.show');
    Route::post('/customers/{customer}/notes', [AdminCustomerController::class, 'note'])->middleware('permission:customers.manage')->name('customers.note');

    Route::middleware('permission:marketing.view')->group(function (): void {
        Route::get('/marketing', [AdminMarketingController::class, 'index'])->name('marketing.index');
        Route::get('/sms', [AdminSmsController::class, 'index'])->name('sms.index');
        Route::get('/promotions', [AdminPromotionController::class, 'index'])->name('promotions.index');
    });
    Route::post('/marketing/campaigns', [AdminMarketingController::class, 'storeCampaign'])->middleware('permission:marketing.manage')->name('marketing.campaigns.store');
    Route::post('/marketing/campaigns/{campaign}/recipients', [AdminMarketingController::class, 'buildRecipients'])->middleware('permission:marketing.manage')->name('marketing.campaigns.recipients');
    Route::post('/marketing/campaigns/{campaign}/stop', [AdminMarketingController::class, 'stop'])->middleware('permission:marketing.manage')->name('marketing.campaigns.stop');
    Route::post('/marketing/segments', [AdminMarketingController::class, 'storeSegment'])->middleware('permission:marketing.manage')->name('marketing.segments.store');
    Route::post('/sms/send', [AdminSmsController::class, 'send'])->middleware('permission:marketing.send')->name('sms.send');
    Route::post('/promotions', [AdminPromotionController::class, 'store'])->middleware('permission:marketing.manage')->name('promotions.store');
    Route::patch('/promotions/{coupon}/toggle', [AdminPromotionController::class, 'toggle'])->middleware('permission:marketing.manage')->name('promotions.toggle');

    Route::get('/support', [AdminSupportController::class, 'index'])->middleware('permission:customers.view')->name('support.index');
    Route::get('/support/{ticket}', [AdminSupportController::class, 'show'])->middleware('permission:customers.view')->name('support.show');
    Route::post('/support/{ticket}/reply', [AdminSupportController::class, 'reply'])->middleware('permission:customers.manage')->name('support.reply');
    Route::post('/support/{ticket}/resolve', [AdminSupportController::class, 'resolve'])->middleware('permission:customers.manage')->name('support.resolve');

    Route::get('/menu', [AdminMegaMenuController::class, 'index'])->middleware('permission:content.manage')->name('menu.index');
    Route::post('/menu', [AdminMegaMenuController::class, 'store'])->middleware('permission:content.manage')->name('menu.store');
    Route::delete('/menu/{item}', [AdminMegaMenuController::class, 'destroy'])->middleware('permission:content.manage')->name('menu.destroy');
    Route::get('/content', [AdminOperationsController::class, 'content'])->middleware('permission:content.view')->name('content.index');
    Route::get('/reports', [AdminOperationsController::class, 'reports'])->middleware('permission:reports.view')->name('reports.index');
    Route::get('/reports/sales.csv', [AdminOperationsController::class, 'salesCsv'])->middleware('permission:reports.export')->name('reports.sales-csv');

    Route::get('/security', [AdminSecurityController::class, 'index'])->middleware('permission:security.manage')->name('security.index');
    Route::put('/security/users/{user}/roles', [AdminSecurityController::class, 'roles'])->middleware('permission:security.manage')->name('security.users.roles');
    Route::post('/security/roles', [AdminSecurityController::class, 'storeRole'])->middleware('permission:security.manage')->name('security.roles.store');
    Route::post('/security/ip-rules', [AdminSecurityController::class, 'storeIpRule'])->middleware('permission:security.manage')->name('security.ip-rules.store');
    Route::post('/security/users/{user}/devices/{device}/revoke', [AdminSecurityController::class, 'revokeDevice'])->middleware('permission:security.manage')->name('security.devices.revoke');
    Route::post('/security/users/{user}/2fa/reset', [AdminSecurityController::class, 'resetTwoFactor'])->middleware('permission:security.manage')->name('security.2fa.reset');

    Route::get('/settings', [AdminSettingsController::class, 'index'])->middleware('permission:settings.view')->name('settings.index');
    Route::put('/settings', [AdminSettingsController::class, 'update'])->middleware('permission:settings.manage')->name('settings.update');
});
