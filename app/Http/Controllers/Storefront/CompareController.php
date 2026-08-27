<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Models\Product;
use App\Domain\Customer\Services\WishlistCompareService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompareController extends Controller
{
    /** Shows a maximum-four-product comparison matrix with technical specifications. */
    public function index(Request $request, WishlistCompareService $lists): View
    {
        $list = $lists->compare($request->user()?->id, $request->session()->get('compare_token'));
        $request->session()->put('compare_token', $list->session_token);
        $list->load(['products.media', 'products.brand', 'products.attributeValues.attribute']);
        return view('storefront.compare', compact('list'));
    }

    /** Adds a slug-bound published product to the current browser comparison list. */
    public function store(Request $request, Product $product, WishlistCompareService $lists): RedirectResponse
    {
        abort_unless(Product::query()->published()->whereKey($product->id)->exists(), 404);
        $list = $lists->compare($request->user()?->id, $request->session()->get('compare_token'));
        $request->session()->put('compare_token', $list->session_token);
        $lists->addCompare($list, $product);
        return back()->with('success', 'محصول به مقایسه اضافه شد.');
    }

    /** Removes a slug-bound product from the current comparison list. */
    public function destroy(Request $request, Product $product, WishlistCompareService $lists): RedirectResponse
    {
        $list = $lists->compare($request->user()?->id, $request->session()->get('compare_token'));
        $lists->removeCompare($list, $product);
        return back()->with('success', 'محصول از مقایسه حذف شد.');
    }
}
