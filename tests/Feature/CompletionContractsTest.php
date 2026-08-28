<?php

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Order\Models\Order;
use App\Domain\Payment\Services\CashbackService;
use App\Domain\Payment\Services\PaymentService;
use App\Domain\Promotion\Models\Coupon;
use App\Domain\Promotion\Services\CouponService;
use App\Domain\Promotion\Services\PricingService;
use App\Models\Role;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Database\Seeders\AccessControlSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('calculates buy-two-get-one BOGO against eligible cart units', function (): void {
    $product = Product::query()->create([
        'name' => 'فیلتر BOGO',
        'slug' => 'bogo-filter',
        'sku' => 'BOGO-1',
        'authenticity' => 'company',
        'status' => 'active',
        'sale_price' => 100000,
    ]);
    $cart = Cart::query()->create(['token' => (string) Str::uuid(), 'status' => 'active']);
    app(CartService::class)->add($cart, $product, 3);
    $coupon = Coupon::query()->create([
        'code' => 'BUY2GET1',
        'name' => 'دو بخر یکی هدیه',
        'type' => 'bogo',
        'value' => 0,
        'conditions' => ['buy_quantity' => 2, 'get_quantity' => 1, 'get_discount_percent' => 100],
        'is_active' => true,
    ]);

    expect(app(CouponService::class)->discountForCart($coupon, $cart->fresh('items.product.categories')))->toBe(100000);
});

it('uses variant wholesale price and centrally configured rounding', function (): void {
    $user = User::factory()->create();
    $product = Product::query()->create([
        'name' => 'قطعه همکاری',
        'slug' => 'wholesale-variant-part',
        'sku' => 'WHOLESALE-P',
        'authenticity' => 'company',
        'status' => 'active',
        'sale_price' => 100000,
        'wholesale_price' => 90000,
    ]);
    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'name' => 'مدل همکاری',
        'sku' => 'WHOLESALE-V',
        'sale_price' => 95000,
        'wholesale_price' => 81000,
        'is_active' => true,
    ]);
    DB::table('wholesale_accounts')->insert([
        'user_id' => $user->id,
        'status' => 'approved',
        'discount_percent' => 10,
        'credit_limit' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
    $settings = app(SettingsRepository::class);
    $settings->set('pricing.rounding_step', 1000, 'pricing', 'int');
    $settings->set('pricing.rounding_mode', 'down', 'pricing');

    $price = app(PricingService::class)->price($product, $variant, 1, $user->id, false);

    expect($price['pricing_tier'])->toBe('wholesale')
        ->and($price['final_price'])->toBe(72000)
        ->and($price['rounding_step'])->toBe(1000);
});

it('records immutable price history when a managed price changes', function (): void {
    $product = Product::query()->create([
        'name' => 'قطعه تاریخچه',
        'slug' => 'price-history-part',
        'sku' => 'HISTORY-1',
        'authenticity' => 'company',
        'status' => 'active',
        'purchase_price' => 50000,
        'sale_price' => 70000,
        'wholesale_price' => 65000,
    ]);

    $product->update(['sale_price' => 75000]);

    $history = DB::table('price_histories')->where('product_id', $product->id)->latest('id')->first();
    expect($history)->not->toBeNull()
        ->and((int) $history->sale_price)->toBe(75000)
        ->and((int) $history->purchase_price)->toBe(50000);
});

it('credits cashback only once for the same paid order reference', function (): void {
    $user = User::factory()->create();
    app(SettingsRepository::class)->set('wallet.cashback_percent', 10, 'wallet', 'int');
    $order = Order::query()->create([
        'number' => 'AC-CASHBACK-1',
        'user_id' => $user->id,
        'status' => 'paid',
        'source' => 'web',
        'subtotal' => 100000,
        'discount_total' => 0,
        'shipping_total' => 0,
        'tax_total' => 0,
        'grand_total' => 100000,
        'shipping_address' => [],
        'billing_address' => [],
    ]);

    $service = app(CashbackService::class);
    expect($service->grant($order))->toBe(10000)
        ->and($service->grant($order))->toBe(10000)
        ->and(DB::table('wallet_entries')->where('reference_type', 'cashback_order')->where('reference_id', $order->id)->count())->toBe(1);
});

it('creates only one payment transaction for a repeated idempotency key', function (): void {
    $user = User::factory()->create();
    $order = Order::query()->create([
        'number' => 'AC-IDEMPOTENT-1',
        'user_id' => $user->id,
        'status' => 'pending_payment',
        'source' => 'web',
        'subtotal' => 100000,
        'discount_total' => 0,
        'shipping_total' => 0,
        'tax_total' => 0,
        'grand_total' => 100000,
        'shipping_address' => [],
        'billing_address' => [],
    ]);

    $payments = app(PaymentService::class);
    $first = $payments->initiate($order, 'card_to_card', 'https://example.test/callback', 'same-key');
    $second = $payments->initiate($order, 'card_to_card', 'https://example.test/callback', 'same-key');

    expect($first['transaction']->id)->toBe($second['transaction']->id)
        ->and(DB::table('payment_transactions')->where('idempotency_key', 'same-key')->count())->toBe(1);
});

it('seeds a least-privilege permission matrix for operational roles', function (): void {
    $this->seed(AccessControlSeeder::class);

    $super = Role::query()->where('slug', 'super-admin')->firstOrFail();
    $support = Role::query()->where('slug', 'support')->firstOrFail();
    $accountant = Role::query()->where('slug', 'accountant')->firstOrFail();

    expect($super->permissions()->count())->toBeGreaterThan(20)
        ->and($support->permissions()->where('slug', 'finance.refund')->exists())->toBeFalse()
        ->and($accountant->permissions()->where('slug', 'finance.refund')->exists())->toBeTrue();
});
