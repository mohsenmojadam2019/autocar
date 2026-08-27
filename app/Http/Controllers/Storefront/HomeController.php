<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Content\Services\MegaMenuService;
use App\Domain\Vehicle\Models\VehicleMake;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    /** Builds the storefront landing page from published catalog/content records only. */
    public function __invoke(MegaMenuService $menus): View
    {
        return view('storefront.home', [
            'menu' => $menus->tree(),
            'categories' => Category::query()->visible()->whereNull('parent_id')->orderBy('position')->limit(12)->get(),
            'products' => Product::query()->published()->with(['brand','media'])->latest('published_at')->limit(12)->get(),
            'brands' => Brand::query()->visible()->orderBy('name')->limit(16)->get(),
            'vehicleMakes' => VehicleMake::query()->where('is_active', true)->orderBy('name')->limit(20)->get(),
            'banners' => DB::table('banners')->where('placement','home_hero')->where('is_active',true)
                ->where(fn($q)=>$q->whereNull('starts_at')->orWhere('starts_at','<=',now()))
                ->where(fn($q)=>$q->whereNull('ends_at')->orWhere('ends_at','>=',now()))->orderBy('position')->get(),
        ]);
    }
}
