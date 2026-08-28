<?php

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Services\ShippingProviderManager;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Http;

it('exposes published products and categories by slug and rejects numeric product ids', function (): void {
    $category = Category::query()->create(['name' => 'ترمز API', 'slug' => 'api-brakes', 'is_active' => true]);
    $product = Product::query()->create([
        'name' => 'لنت API', 'slug' => 'api-brake-pad', 'sku' => 'API-PAD-1',
        'authenticity' => 'company', 'status' => 'active', 'sale_price' => 123000,
    ]);
    $product->categories()->sync([$category->id]);

    $this->getJson('/api/v1/products/'.$product->slug)
        ->assertOk()->assertJsonPath('data.slug', $product->slug)->assertJsonPath('meta.api_version', 'v1');
    $this->getJson('/api/v1/products/'.$product->id)->assertNotFound();
    $this->getJson('/api/v1/categories/'.$category->slug.'/products')
        ->assertOk()->assertJsonPath('data.0.slug', $product->slug)->assertJsonPath('meta.api_version', 'v1');
    $this->getJson('/api/v1/categories/'.$category->id.'/products')->assertNotFound();
});

it('mutates a guest api cart through product slugs without exposing item ids', function (): void {
    $product = Product::query()->create([
        'name' => 'کارت API', 'slug' => 'api-cart-part', 'sku' => 'API-CART-1',
        'authenticity' => 'company', 'status' => 'active', 'sale_price' => 42000,
    ]);

    $this->postJson('/api/v1/cart/'.$product->slug, ['quantity' => 2])
        ->assertCreated()->assertJsonPath('data.items.0.product_slug', $product->slug)->assertJsonMissingPath('data.items.0.product_id');
    $this->putJson('/api/v1/cart/'.$product->slug, ['quantity' => 3])
        ->assertOk()->assertJsonPath('data.items.0.quantity', 3);
    $this->deleteJson('/api/v1/cart/'.$product->slug)
        ->assertOk()->assertJsonCount(0, 'data.items');
});

it('normalizes a configurable Post carrier create and tracking API', function (): void {
    $user = User::factory()->create();
    $order = Order::query()->create([
        'number' => 'AC-SHIP-API', 'user_id' => $user->id, 'status' => 'paid', 'source' => 'web',
        'subtotal' => 1000, 'discount_total' => 0, 'shipping_total' => 0, 'tax_total' => 0, 'grand_total' => 1000,
        'shipping_address' => ['address' => 'تهران'], 'billing_address' => [],
    ]);
    $settings = app(SettingsRepository::class);
    $settings->set('shipping.providers.post.mode', 'api', 'shipping');
    $settings->set('shipping.providers.post.base_url', 'https://post.example.test', 'shipping');
    $settings->set('shipping.providers.post.create_endpoint', '/shipments', 'shipping');
    $settings->set('shipping.providers.post.track_endpoint', '/track/{tracking_code}', 'shipping');
    $settings->set('shipping.providers.post.token', 'secret-token', 'shipping', 'string', true);

    Http::fake([
        'https://post.example.test/shipments' => Http::response(['tracking_code' => 'POST-123', 'label_url' => 'https://post.example.test/label/123', 'status' => 'preparing']),
        'https://post.example.test/track/POST-123' => Http::response(['status' => 'delivered', 'location' => 'تهران', 'description' => 'تحویل شد']),
    ]);

    $driver = app(ShippingProviderManager::class)->driver('post');
    $created = $driver->createShipment($order);
    $tracked = $driver->track('POST-123');

    expect($created['tracking_code'])->toBe('POST-123')
        ->and($created['label_url'])->toContain('/label/123')
        ->and($tracked['status'])->toBe('delivered')
        ->and($tracked['location'])->toBe('تهران');
    Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret-token'));
});
