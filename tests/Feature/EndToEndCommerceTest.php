<?php

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Checkout\Services\CheckoutService;
use App\Domain\Inventory\Models\StockItem;
use App\Domain\Inventory\Services\InventoryService;
use App\Domain\Vehicle\Enums\FitmentStatus;
use App\Domain\Vehicle\Models\VehicleTrim;
use App\Domain\Vehicle\Services\FitmentResolver;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

it('completes vehicle fitment through cart checkout and stock reservation', function (): void {
    $user = User::factory()->create(['name' => 'مشتری تست']);
    $user->forceFill(['mobile' => '09120000001', 'account_type' => 'natural', 'national_code' => '0013546789'])->save();

    $makeId = DB::table('vehicle_makes')->insertGetId(['name' => 'تست خودرو', 'slug' => 'test-car', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $modelId = DB::table('vehicle_models')->insertGetId(['vehicle_make_id' => $makeId, 'name' => 'مدل تست', 'slug' => 'test-model', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $generationId = DB::table('vehicle_generations')->insertGetId(['vehicle_model_id' => $modelId, 'name' => 'نسل تست', 'from_year' => 1400, 'to_year' => 1405, 'created_at' => now(), 'updated_at' => now()]);
    $trimId = DB::table('vehicle_trims')->insertGetId(['vehicle_generation_id' => $generationId, 'name' => 'تیپ تست', 'year' => 1403, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    DB::table('customer_vehicles')->insert(['user_id' => $user->id, 'vehicle_trim_id' => $trimId, 'nickname' => 'خودروی من', 'is_default' => true, 'created_at' => now(), 'updated_at' => now()]);

    $product = Product::query()->create([
        'name' => 'لنت سازگار تست', 'slug' => 'compatible-pad', 'sku' => 'FIT-1',
        'authenticity' => 'company', 'status' => 'active', 'sale_price' => 500000,
        'is_taxable' => false, 'weight_grams' => 500,
    ]);
    DB::table('product_fitments')->insert([
        'product_id' => $product->id, 'vehicle_trim_id' => $trimId, 'status' => 'compatible',
        'is_exclusion' => false, 'confidence' => 100, 'created_at' => now(), 'updated_at' => now(),
    ]);
    $warehouseId = DB::table('warehouses')->insertGetId(['name' => 'انبار تست', 'code' => 'WH-E2E', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $stockId = DB::table('stock_items')->insertGetId(['warehouse_id' => $warehouseId, 'product_id' => $product->id, 'on_hand' => 5, 'reserved' => 0, 'damaged' => 0, 'reorder_point' => 1, 'created_at' => now(), 'updated_at' => now()]);

    $trim = VehicleTrim::query()->findOrFail($trimId);
    expect(app(FitmentResolver::class)->resolve($product, $trim)->status)->toBe(FitmentStatus::Compatible);

    $cart = Cart::query()->create(['token' => (string) Str::uuid(), 'user_id' => $user->id, 'status' => 'active']);
    app(CartService::class)->add($cart, $product, 2);
    $order = app(CheckoutService::class)->createOrder($cart->fresh(), [
        'full_name' => $user->name, 'mobile' => $user->mobile, 'province' => 'تهران',
        'city' => 'تهران', 'postal_code' => '1234567890', 'address' => 'نشانی تست',
    ]);

    expect($order->status->value)->toBe('pending_payment')
        ->and($order->items()->count())->toBe(1)
        ->and((int) StockItem::query()->findOrFail($stockId)->reserved)->toBe(2)
        ->and((int) DB::table('inventory_reservations')->where('order_id', $order->id)->where('status', 'reserved')->sum('quantity'))->toBe(2)
        ->and($cart->fresh()->status)->toBe('converted');
});

it('prevents a second reservation from overselling the same locked stock row', function (): void {
    $product = Product::query()->create(['name' => 'موجودی تست', 'slug' => 'stock-race', 'sku' => 'STOCK-RACE', 'authenticity' => 'company', 'status' => 'active', 'sale_price' => 1000]);
    $warehouseId = DB::table('warehouses')->insertGetId(['name' => 'انبار Race', 'code' => 'WH-RACE', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
    $stockId = DB::table('stock_items')->insertGetId(['warehouse_id' => $warehouseId, 'product_id' => $product->id, 'on_hand' => 5, 'reserved' => 0, 'damaged' => 0, 'reorder_point' => 0, 'created_at' => now(), 'updated_at' => now()]);

    $inventory = app(InventoryService::class);
    $inventory->reserve($stockId, 4, 'test', 1);

    expect(fn () => $inventory->reserve($stockId, 2, 'test', 2))->toThrow(RuntimeException::class)
        ->and((int) StockItem::query()->findOrFail($stockId)->reserved)->toBe(4);
});
