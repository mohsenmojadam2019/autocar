<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Models\Banner;
use App\Domain\Content\Services\MegaMenuService;
use App\Domain\Vehicle\Models\VehicleMake;
use App\Http\Controllers\Controller;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** Builds the storefront landing page with scheduled content, active promotions and slug-first catalog navigation. */
    public function __invoke(MegaMenuService $menus): View
    {
        $productCandidates = Product::query()
            ->published()
            ->with(['brand', 'media', 'categories'])
            ->latest('published_at')
            ->limit(32)
            ->get();
        $specialProducts = $productCandidates
            ->filter(fn (Product $product) => $product->priceSnapshot()['discount_amount'] > 0)
            ->take(8)
            ->values();

        return view('storefront.home', [
            'menu' => $menus->tree(),
            'categories' => Category::query()->visible()->whereNull('parent_id')->orderBy('position')->limit(12)->get(),
            'products' => $productCandidates->take(12),
            'specialProducts' => $specialProducts,
            'brands' => Brand::query()->visible()->orderBy('name')->limit(20)->get(),
            'vehicleMakes' => VehicleMake::query()->where('is_active', true)->orderBy('name')->limit(20)->get(),
            'heroBanners' => Banner::query()->visible()->placement('home_hero')->limit(5)->get(),
            'middleBanners' => Banner::query()->visible()->placement('home_middle')->limit(3)->get(),
            'bottomBanners' => Banner::query()->visible()->placement('home_bottom')->limit(3)->get(),
        ]);
    }
}
