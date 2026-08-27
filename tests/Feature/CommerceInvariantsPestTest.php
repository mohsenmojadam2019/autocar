<?php

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Invoice\Services\InvoiceService;
use App\Domain\Order\Models\Order;
use App\Domain\Promotion\Services\PricingService;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('merges a guest cart into the existing customer cart without losing quantities', function (): void {
    $user = User::factory()->create();
    $product = Product::query()->create(['name' => 'فیلتر تست', 'slug' => 'test-filter', 'sku' => 'TF-1', 'authenticity' => 'company', 'status' => 'active', 'sale_price' => 1000]);
    $guest = Cart::query()->create(['token' => (string) Str::uuid(), 'status' => 'active']);
    $customer = Cart::query()->create(['token' => (string) Str::uuid(), 'user_id' => $user->id, 'status' => 'active']);
    $service = app(CartService::class);
    $service->add($guest, $product, 2);
    $service->add($customer, $product, 1);
    $merged = $service->claimAfterLogin($user->id, $guest->token);
    expect($merged->items()->where('product_id', $product->id)->value('quantity'))->toBe(3)
        ->and($guest->fresh()->status)->toBe('merged');
});

it('applies approved wholesale pricing through the authoritative pricing engine', function (): void {
    $user = User::factory()->create();
    $product = Product::query()->create(['name' => 'لنت عمده', 'slug' => 'b2b-pad', 'sku' => 'B2B-1', 'authenticity' => 'company', 'status' => 'active', 'sale_price' => 100000, 'wholesale_price' => 80000]);
    DB::table('wholesale_accounts')->insert(['user_id' => $user->id, 'status' => 'approved', 'discount_percent' => 10, 'credit_limit' => 0, 'created_at' => now(), 'updated_at' => now()]);
    $price = app(PricingService::class)->price($product, null, 1, $user->id, false);
    expect($price['pricing_tier'])->toBe('wholesale')->and($price['final_price'])->toBe(72000);
});

it('keeps legal invoice identity immutable in the issued snapshot', function (): void {
    $user = User::factory()->create();
    $buyer = ['type' => 'legal', 'company_name' => 'شرکت تست', 'national_id' => '10101010101', 'economic_code' => '411111111111'];
    $order = Order::query()->create(['number' => 'AC-TEST-INV', 'user_id' => $user->id, 'status' => 'pending_payment', 'source' => 'web', 'subtotal' => 1000, 'discount_total' => 0, 'shipping_total' => 0, 'tax_total' => 0, 'grand_total' => 1000, 'invoice_kind' => 'legal', 'billing_profile_snapshot' => $buyer, 'shipping_address' => [], 'billing_address' => []]);
    $invoice = app(InvoiceService::class)->issue($order);
    $snapshot = json_decode($invoice->snapshot, true, flags: JSON_THROW_ON_ERROR);
    $order->update(['billing_profile_snapshot' => ['company_name' => 'شرکت تغییر یافته']]);
    expect($snapshot['invoice_kind'])->toBe('legal')->and($snapshot['buyer']['company_name'])->toBe('شرکت تست');
});
