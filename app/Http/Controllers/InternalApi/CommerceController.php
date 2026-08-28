<?php

namespace App\Http\Controllers\InternalApi;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Domain\Order\Models\Order;
use App\Domain\Vehicle\Models\VehicleTrim;
use App\Domain\Vehicle\Services\FitmentResolver;
use App\Http\Controllers\Controller;
use App\Support\JalaliDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CommerceController extends Controller
{
    public function product(Product $product): JsonResponse
    {
        abort_unless(Product::query()->published()->whereKey($product->id)->exists(), 404);
        $product->load(['brand', 'categories', 'media', 'variants', 'attributeValues.attribute', 'attributeValues.option']);

        return $this->json([
            'slug' => $product->slug,
            'name' => $product->name,
            'sku' => $product->sku,
            'oem_code' => $product->oem_code,
            'manufacturer_code' => $product->manufacturer_code,
            'brand' => $product->brand ? ['slug' => $product->brand->slug, 'name' => $product->brand->name] : null,
            'categories' => $product->categories->map(fn ($category) => ['slug' => $category->slug, 'name' => $category->name])->values(),
            'price' => $product->priceSnapshot(),
            'variants' => $product->variants->map(fn ($variant) => ['sku' => $variant->sku, 'name' => $variant->name, 'sale_price' => (int) $variant->sale_price])->values(),
            'media' => $product->media->map(fn ($media) => ['path' => $media->path, 'alt' => $media->alt, 'type' => $media->type])->values(),
        ]);
    }

    public function category(Category $category, Request $request): JsonResponse
    {
        abort_unless($category->is_active, 404);
        $products = $category->products()
            ->where('products.status', 'active')
            ->where(fn ($query) => $query->whereNull('products.published_at')->orWhere('products.published_at', '<=', now()))
            ->orderByDesc('products.id')
            ->paginate(min(50, max(10, (int) $request->input('per_page', 24))));

        return $this->json($products->getCollection()->map(fn (Product $product) => [
            'slug' => $product->slug, 'name' => $product->name, 'sku' => $product->sku, 'sale_price' => (int) $product->sale_price,
        ])->values(), ['pagination' => ['page' => $products->currentPage(), 'last_page' => $products->lastPage(), 'total' => $products->total()]]);
    }

    public function fitment(Product $product, VehicleTrim $trim, FitmentResolver $resolver): JsonResponse
    {
        $result = $resolver->resolve($product, $trim);

        return $this->json(['product_slug' => $product->slug, 'status' => $result->status->value, 'message' => $result->message, 'confidence' => $result->confidence]);
    }

    public function cart(Request $request, CartService $carts): JsonResponse
    {
        return $this->json($this->cartPayload($this->resolveCart($request, $carts)));
    }

    public function addCart(Request $request, Product $product, CartService $carts): JsonResponse
    {
        abort_unless(Product::query()->published()->whereKey($product->id)->exists(), 404);
        $data = $request->validate(['quantity' => ['nullable', 'integer', 'min:1', 'max:99'], 'variant_sku' => ['nullable', 'string', 'max:100']]);
        $variant = ! empty($data['variant_sku']) ? ProductVariant::query()->where('product_id', $product->id)->where('sku', $data['variant_sku'])->firstOrFail() : null;
        $cart = $carts->add($this->resolveCart($request, $carts), $product, (int) ($data['quantity'] ?? 1), $variant);

        return $this->json($this->cartPayload($cart), status: 201);
    }

    public function updateCart(Request $request, Product $product, CartService $carts): JsonResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99'], 'variant_sku' => ['nullable', 'string', 'max:100']]);
        $cart = $this->resolveCart($request, $carts);
        $variantId = ! empty($data['variant_sku']) ? ProductVariant::query()->where('product_id', $product->id)->where('sku', $data['variant_sku'])->value('id') : null;
        $line = $cart->items()->where('product_id', $product->id)->where('product_variant_id', $variantId)->firstOrFail();
        $carts->updateQuantity($cart, $line->id, (int) $data['quantity']);

        return $this->json($this->cartPayload($cart->fresh('items.product')));
    }

    public function removeCart(Request $request, Product $product, CartService $carts): JsonResponse
    {
        $data = $request->validate(['variant_sku' => ['nullable', 'string', 'max:100']]);
        $cart = $this->resolveCart($request, $carts);
        $variantId = ! empty($data['variant_sku']) ? ProductVariant::query()->where('product_id', $product->id)->where('sku', $data['variant_sku'])->value('id') : null;
        $cart->items()->where('product_id', $product->id)->where('product_variant_id', $variantId)->delete();

        return $this->json($this->cartPayload($cart->fresh('items.product')));
    }

    public function account(Request $request): JsonResponse
    {
        $user = $request->user();

        return $this->json([
            'name' => $user->name,
            'mobile' => $user->mobile,
            'account_type' => $user->account_type,
            'orders_count' => Order::query()->where('user_id', $user->id)->count(),
            'wallet_balance' => (int) DB::table('wallet_entries')->where('user_id', $user->id)->sum('amount'),
        ]);
    }

    public function orders(Request $request, JalaliDate $jalali): JsonResponse
    {
        $orders = Order::query()->where('user_id', $request->user()->id)->latest()->limit(50)->get();

        return $this->json($orders->map(fn (Order $order) => [
            'number' => $order->number,
            'status' => $order->status->value,
            'grand_total' => (int) $order->grand_total,
            'created_at' => $order->created_at?->toIso8601String(),
            'created_at_jalali' => $order->created_at ? $jalali->format($order->created_at) : null,
        ])->values());
    }

    private function resolveCart(Request $request, CartService $carts): Cart
    {
        $cart = $carts->resolve($request->user()?->id, $request->session()->get('cart_token'));
        $request->session()->put('cart_token', $cart->token);

        return $cart;
    }

    private function cartPayload(Cart $cart): array
    {
        $cart->load(['items.product:id,name,slug,sku,sale_price', 'items.variant:id,product_id,name,sku,sale_price']);

        return [
            'token' => $cart->token,
            'status' => $cart->status,
            'subtotal' => $cart->subtotal(),
            'items' => $cart->items->map(fn ($item) => [
                'product_slug' => $item->product->slug,
                'sku' => $item->variant?->sku ?? $item->product->sku,
                'quantity' => (int) $item->quantity,
                'unit_price' => (int) $item->unit_price,
            ])->values(),
        ];
    }

    private function json(mixed $data, array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json(['data' => $data, 'meta' => array_merge(['api_version' => 'v1'], $meta)], $status);
    }
}
