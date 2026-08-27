<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Services\WishlistCompareService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WishlistController extends Controller
{
    /** Shows the authenticated customer's default wishlist. */
    public function index(Request $request, WishlistCompareService $lists): View
    {
        $wishlist = $lists->wishlist($request->user()->id)->load('products.media');
        return view('storefront.wishlist', compact('wishlist'));
    }

    /** Adds a slug-bound published product to the default wishlist. */
    public function store(Request $request, Product $product, WishlistCompareService $lists): RedirectResponse
    {
        abort_unless(Product::query()->published()->whereKey($product->id)->exists(), 404);
        $lists->addWishlist($request->user()->id, $product);
        return back()->with('success', 'به علاقه‌مندی‌ها اضافه شد.');
    }

    /** Removes a slug-bound product from the default wishlist. */
    public function destroy(Request $request, Product $product, WishlistCompareService $lists): RedirectResponse
    {
        $lists->removeWishlist($request->user()->id, $product);
        return back()->with('success', 'از علاقه‌مندی‌ها حذف شد.');
    }
}
