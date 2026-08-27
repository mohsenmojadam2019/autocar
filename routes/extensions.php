<?php

use App\Http\Controllers\Admin\BannerController as AdminBannerController;
use App\Http\Controllers\Admin\CommerceOperationsController;
use App\Http\Controllers\Admin\ContentManagementController;
use App\Http\Controllers\Admin\ModerationController;
use App\Http\Controllers\Admin\ProductWorkbenchController;
use App\Http\Controllers\Admin\PromotionController;
use App\Http\Controllers\Admin\SearchSeoController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\TwoFactorController;
use App\Http\Controllers\Customer\BillingProfileController;
use App\Http\Controllers\Customer\GarageController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Customer\WholesaleController;
use App\Http\Controllers\Storefront\BannerInteractionController;
use App\Http\Controllers\Storefront\CatalogController;
use App\Http\Controllers\Storefront\CheckoutController;
use App\Http\Controllers\Storefront\SeoController;
use Illuminate\Support\Facades\Route;

Route::get('/brand/{brand}', [CatalogController::class, 'brand'])->name('brand.show');
Route::get('/sitemap.xml', [SeoController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SeoController::class, 'robots'])->name('robots');
Route::post('/banners/{banner}/impression', [BannerInteractionController::class, 'impression'])->middleware('throttle:120,1')->name('banners.impression');
Route::get('/banners/{banner}/click', [BannerInteractionController::class, 'click'])->middleware('throttle:60,1')->name('banners.click');

Route::middleware('guest')->group(function (): void {
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store'])->middleware('throttle:10,1')->name('register.store');
});

Route::middleware('auth')->group(function (): void {
    Route::get('/two-factor/challenge', [TwoFactorController::class, 'challenge'])->name('2fa.challenge');
    Route::post('/two-factor/challenge', [TwoFactorController::class, 'verifyChallenge'])->middleware('throttle:10,1')->name('2fa.verify');
    Route::get('/checkout/shipping-rates', [CheckoutController::class, 'shippingRates'])->name('checkout.shipping-rates');

    Route::prefix('account')->name('account.')->group(function (): void {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile');
        Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::get('/billing-profiles', [BillingProfileController::class, 'index'])->name('billing.index');
        Route::post('/billing-profiles', [BillingProfileController::class, 'store'])->name('billing.store');
        Route::put('/billing-profiles/{profile}', [BillingProfileController::class, 'update'])->name('billing.update');
        Route::delete('/billing-profiles/{profile}', [BillingProfileController::class, 'destroy'])->name('billing.destroy');
        Route::post('/garage/{vehicle}/activate', [GarageController::class, 'activate'])->name('garage.activate');
        Route::get('/security/two-factor', [TwoFactorController::class, 'setup'])->name('2fa.setup');
        Route::post('/security/two-factor', [TwoFactorController::class, 'confirm'])->middleware('throttle:10,1')->name('2fa.confirm');
        Route::delete('/security/two-factor', [TwoFactorController::class, 'disable'])->name('2fa.disable');
        Route::get('/wholesale', [WholesaleController::class, 'index'])->name('wholesale.index');
        Route::post('/wholesale/apply', [WholesaleController::class, 'apply'])->name('wholesale.apply');
        Route::post('/wholesale/quote', [WholesaleController::class, 'quote'])->name('wholesale.quote');
        Route::get('/wholesale/{number}/proforma', [WholesaleController::class, 'proforma'])->name('wholesale.proforma');
    });
});

Route::prefix('admin')->name('admin.')->middleware('auth')->group(function (): void {
    Route::post('/promotions/automatic', [PromotionController::class, 'storeAutomatic'])->middleware('permission:marketing.manage')->name('promotions.automatic.store');
    Route::patch('/promotions/automatic/{promotion}/toggle', [PromotionController::class, 'toggleAutomatic'])->middleware('permission:marketing.manage')->name('promotions.automatic.toggle');
    Route::delete('/promotions/automatic/{promotion}', [PromotionController::class, 'destroyAutomatic'])->middleware('permission:marketing.manage')->name('promotions.automatic.destroy');

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

    Route::prefix('procurement')->name('procurement.')->middleware('permission:inventory.manage')->group(function (): void {
        Route::get('/', [CommerceOperationsController::class, 'procurement'])->name('index');
        Route::post('/suppliers', [CommerceOperationsController::class, 'storeSupplier'])->name('suppliers.store');
        Route::post('/orders', [CommerceOperationsController::class, 'storePurchaseOrder'])->name('orders.store');
        Route::post('/orders/{purchaseOrder}/receive', [CommerceOperationsController::class, 'receivePurchaseOrder'])->name('orders.receive');
    });

    Route::prefix('fulfillment')->name('shipping.')->middleware('permission:orders.manage')->group(function (): void {
        Route::get('/', [CommerceOperationsController::class, 'shipping'])->name('index');
        Route::post('/methods', [CommerceOperationsController::class, 'storeShippingMethod'])->name('methods.store');
        Route::post('/zones', [CommerceOperationsController::class, 'storeShippingZone'])->name('zones.store');
        Route::post('/rates', [CommerceOperationsController::class, 'storeShippingRate'])->name('rates.store');
        Route::post('/shipments', [CommerceOperationsController::class, 'storeShipment'])->name('shipments.store');
        Route::patch('/shipments/{shipment}', [CommerceOperationsController::class, 'updateShipment'])->name('shipments.update');
    });

    Route::prefix('rma')->name('rma.')->middleware('permission:orders.refund')->group(function (): void {
        Route::get('/', [CommerceOperationsController::class, 'returns'])->name('index');
        Route::post('/{return}/approve', [CommerceOperationsController::class, 'approveReturn'])->name('approve');
        Route::post('/{return}/reject', [CommerceOperationsController::class, 'rejectReturn'])->name('reject');
    });

    Route::prefix('wholesale')->name('wholesale.')->middleware('permission:customers.manage')->group(function (): void {
        Route::get('/', [CommerceOperationsController::class, 'wholesale'])->name('index');
        Route::post('/accounts/{account}', [CommerceOperationsController::class, 'reviewWholesale'])->name('review');
    });

    Route::prefix('editorial')->name('editorial.')->middleware('permission:content.manage')->group(function (): void {
        Route::get('/', [ContentManagementController::class, 'index'])->name('index');
        Route::post('/pages', [ContentManagementController::class, 'storePage'])->name('pages.store');
        Route::put('/pages/{page}', [ContentManagementController::class, 'updatePage'])->name('pages.update');
        Route::delete('/pages/{page}', [ContentManagementController::class, 'destroyPage'])->name('pages.destroy');
        Route::post('/posts', [ContentManagementController::class, 'storePost'])->name('posts.store');
        Route::put('/posts/{post}', [ContentManagementController::class, 'updatePost'])->name('posts.update');
        Route::delete('/posts/{post}', [ContentManagementController::class, 'destroyPost'])->name('posts.destroy');
        Route::post('/faqs', [ContentManagementController::class, 'storeFaq'])->name('faqs.store');
        Route::put('/faqs/{faq}', [ContentManagementController::class, 'updateFaq'])->name('faqs.update');
        Route::get('/revisions/{type}/{slug}', [ContentManagementController::class, 'revisions'])->name('revisions');
        Route::post('/revisions/{type}/{slug}/{revision}', [ContentManagementController::class, 'restoreRevision'])->name('revisions.restore');
    });

    Route::prefix('moderation')->name('moderation.')->middleware('permission:content.manage')->group(function (): void {
        Route::get('/', [ModerationController::class, 'index'])->name('index');
        Route::patch('/reviews/{review}', [ModerationController::class, 'review'])->name('reviews');
        Route::patch('/reports/{report}', [ModerationController::class, 'report'])->name('reports');
        Route::patch('/questions/{question}', [ModerationController::class, 'question'])->name('questions');
    });

    Route::prefix('search-seo')->name('search-seo.')->middleware('permission:content.manage')->group(function (): void {
        Route::get('/', [SearchSeoController::class, 'index'])->name('index');
        Route::post('/synonyms', [SearchSeoController::class, 'storeSynonym'])->name('synonyms.store');
        Route::delete('/synonyms/{synonym}', [SearchSeoController::class, 'destroySynonym'])->name('synonyms.destroy');
        Route::post('/redirects', [SearchSeoController::class, 'storeRedirect'])->name('redirects.store');
        Route::patch('/redirects/{redirect}', [SearchSeoController::class, 'toggleRedirect'])->name('redirects.toggle');
    });
});
