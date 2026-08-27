<?php

namespace Tests\Feature;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SlugRoutingTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_route_resolves_slug_and_rejects_numeric_id(): void
    {
        $product = Product::query()->create([
            'name' => 'لنت تست',
            'slug' => 'test-brake-pad',
            'sku' => 'TEST-001',
            'authenticity' => 'company',
            'status' => 'active',
            'sale_price' => 100000,
        ]);

        $this->get('/product/'.$product->slug)->assertOk();
        $this->get('/product/'.$product->id)->assertNotFound();
        $this->post('/cart/'.$product->id, ['quantity' => 1])->assertNotFound();
    }

    public function test_category_route_resolves_slug_and_rejects_numeric_id(): void
    {
        $category = Category::query()->create([
            'name' => 'سیستم ترمز',
            'slug' => 'brake-system',
            'is_active' => true,
        ]);

        $this->get('/category/'.$category->slug)->assertOk();
        $this->get('/category/'.$category->id)->assertNotFound();
    }

    public function test_models_expose_slug_as_route_key(): void
    {
        $this->assertSame('slug', (new Product())->getRouteKeyName());
        $this->assertSame('slug', (new Category())->getRouteKeyName());
    }
}
