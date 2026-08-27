<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductVariant;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CartController extends Controller
{
    public function index(Request $request, CartService $service): View
    {
        $cart = $this->cart($request, $service)->load(['items.product.media', 'items.variant']);

        return view('storefront.cart.index', compact('cart'));
    }

    public function add(Request $request, Product $product, CartService $service): RedirectResponse
    {
        abort_unless($product->newQuery()->published()->whereKey($product->id)->exists(), 404);
        $data = $request->validate(['quantity' => ['nullable', 'integer', 'min:1', 'max:99'], 'variant_id' => ['nullable', 'integer']]);
        $variant = isset($data['variant_id']) ? ProductVariant::query()->where('product_id', $product->id)->whereKey($data['variant_id'])->firstOrFail() : null;
        $service->add($this->cart($request, $service), $product, (int) ($data['quantity'] ?? 1), $variant);

        return back()->with('success', 'محصول به سبد خرید اضافه شد.');
    }

    public function update(Request $request, int $item, CartService $service): RedirectResponse
    {
        $data = $request->validate(['quantity' => ['required', 'integer', 'min:1', 'max:99']]);
        $service->updateQuantity($this->cart($request, $service), $item, (int) $data['quantity']);

        return back()->with('success', 'سبد خرید و قیمت به‌روزرسانی شد.');
    }

    public function remove(Request $request, int $item, CartService $service): RedirectResponse
    {
        $this->cart($request, $service)->items()->whereKey($item)->delete();

        return back()->with('success', 'محصول از سبد حذف شد.');
    }

    private function cart(Request $request, CartService $service): Cart
    {
        $cart = $service->resolve($request->user()?->id, $request->session()->get('cart_token'));
        $request->session()->put('cart_token', $cart->token);

        return $cart;
    }
}
