<?php

namespace App\Http\Controllers\InternalApi;

use App\Domain\Cart\Models\Cart;
use App\Domain\Cart\Services\CartService;
use App\Domain\Catalog\Models\Product;
use App\Domain\Order\Models\Order;
use App\Domain\Vehicle\Models\VehicleTrim;
use App\Domain\Vehicle\Services\FitmentResolver;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommerceController extends Controller
{
    /** Returns product fitment for an exact vehicle trim. */
    public function fitment(Product $product, VehicleTrim $trim, FitmentResolver $resolver): JsonResponse
    {
        $result = $resolver->resolve($product, $trim);

        return response()->json(['data' => ['product_slug' => $product->slug, 'status' => $result->status->value, 'message' => $result->message, 'confidence' => $result->confidence]]);
    }

    /** Returns the authenticated customer's active cart in a stable JSON envelope. */
    public function cart(Request $request, CartService $carts): JsonResponse
    {
        $cart = $carts->resolve($request->user()->id, $request->session()->get('cart_token'));
        $request->session()->put('cart_token', $cart->token);
        $cart->load(['items.product:id,name,slug,sku,sale_price', 'items.variant:id,product_id,name,sku,sale_price']);

        return response()->json(['data' => $cart]);
    }

    /** Returns customer-owned orders without exposing other users' records. */
    public function orders(Request $request): JsonResponse
    {
        $orders = Order::query()->where('user_id', $request->user()->id)->latest()->limit(50)->get();

        return response()->json(['data' => $orders]);
    }
}
