<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\ProductRecommendationService;
use App\Domain\Search\Services\ProductSearchService;
use App\Domain\Vehicle\Models\CustomerVehicle;
use App\Domain\Vehicle\Services\FitmentResolver;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CatalogController extends Controller
{
    /** Lists a slug-bound category with safe brand/price filters and stable pagination. */
    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);
        $query = $category->products()->published()->with(['brand', 'media']);
        $this->applyFilters($query, $request);

        return view('storefront.catalog.index', ['title' => $category->name, 'category' => $category, 'products' => $query->paginate(24)->withQueryString(), 'brands' => Brand::query()->visible()->orderBy('name')->get(), 'breadcrumbs' => $category->breadcrumb()]);
    }

    /** Lists a slug-bound brand landing page without exposing numeric identifiers. */
    public function brand(Request $request, Brand $brand): View
    {
        abort_unless($brand->is_active, 404);
        $query = Product::query()->published()->where('brand_id', $brand->id)->with(['brand', 'media']);
        $this->applyFilters($query, $request, false);

        return view('storefront.catalog.index', ['title' => 'قطعات '.$brand->name, 'category' => null, 'brand' => $brand, 'products' => $query->paginate(24)->withQueryString(), 'brands' => Brand::query()->visible()->orderBy('name')->get(), 'breadcrumbs' => [['name' => 'برندها', 'url' => route('home').'#brands'], ['name' => $brand->name, 'url' => null]]]);
    }

    /** Renders slug-filtered results and persists user/session search history. */
    public function search(Request $request, ProductSearchService $search): View
    {
        $data = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'brand_slug' => ['nullable', 'string', 'max:190', 'exists:brands,slug'], 'category_slug' => ['nullable', 'string', 'max:190', 'exists:categories,slug']]);
        $term = trim($data['q'] ?? '');
        $products = $term !== ''
            ? $search->search($term, $data, 24, $request->user()?->id, $request->session()->getId())
            : Product::query()->published()->with(['brand', 'media'])
                ->when($data['brand_slug'] ?? null, fn (Builder $q, string $slug) => $q->whereHas('brand', fn (Builder $b) => $b->where('slug', $slug)))
                ->when($data['category_slug'] ?? null, fn (Builder $q, string $slug) => $q->whereHas('categories', fn (Builder $c) => $c->where('slug', $slug)))
                ->latest('published_at')->paginate(24)->withQueryString();

        return view('storefront.catalog.index', [
            'title' => $term !== '' ? 'نتایج جست‌وجوی «'.$term.'»' : 'همه قطعات',
            'products' => $products,
            'brands' => Brand::query()->visible()->orderBy('name')->get(),
            'breadcrumbs' => [],
            'category' => null,
            'searchHistory' => $search->history($request->user()?->id, $request->session()->getId()),
        ]);
    }

    /** Shows a product with timed pricing, merchandising, reviews and active-garage fitment. */
    public function product(Request $request, Product $product, ProductRecommendationService $recommendations, FitmentResolver $fitmentResolver): View
    {
        abort_unless(Product::query()->published()->whereKey($product->getKey())->exists(), 404);
        $product->load(['brand', 'media', 'categories', 'variants', 'attributeValues.attribute', 'attributeValues.option', 'fitments']);
        $rating = DB::table('reviews')->where('product_id', $product->id)->where('status', 'approved')->selectRaw('COUNT(*) as reviews_count, COALESCE(AVG(rating), 0) as rating_average')->first();
        $activeVehicle = null;
        $activeFitment = null;
        if ($request->user() && $request->session()->get('active_vehicle_id')) {
            $activeVehicle = CustomerVehicle::query()->whereKey($request->session()->get('active_vehicle_id'))->where('user_id', $request->user()->id)->with('trim.generation.model.make')->first();
            if ($activeVehicle?->trim) {
                $activeFitment = $fitmentResolver->resolve($product, $activeVehicle->trim);
            }
        }

        return view('storefront.product.show', [
            'product' => $product, 'price' => $product->priceSnapshot(), 'similar' => $recommendations->similar($product),
            'complementary' => $recommendations->complementary($product), 'alternatives' => $recommendations->alternatives($product), 'upsells' => $recommendations->upsells($product),
            'reviews' => DB::table('reviews')->where('product_id', $product->id)->where('status', 'approved')->latest()->limit(20)->get(),
            'questions' => DB::table('product_questions')->where('product_id', $product->id)->where('status', 'approved')->latest()->limit(20)->get(),
            'ratingAverage' => (float) ($rating?->rating_average ?? 0), 'reviewsCount' => (int) ($rating?->reviews_count ?? 0),
            'activeVehicle' => $activeVehicle, 'activeFitment' => $activeFitment,
        ]);
    }

    /** Applies bounded catalog filters and sort keys. */
    private function applyFilters(Builder|BelongsToMany $query, Request $request, bool $allowBrand = true): void
    {
        if ($allowBrand) {
            $query->when($request->string('brand_slug')->toString(), fn ($builder, string $slug) => $builder->whereHas('brand', fn ($brand) => $brand->where('slug', $slug)));
        }
        $query->when($request->integer('min_price'), fn ($builder, int $value) => $builder->where('sale_price', '>=', $value))->when($request->integer('max_price'), fn ($builder, int $value) => $builder->where('sale_price', '<=', $value));
        match ($request->string('sort')->toString()) {
            'price_asc' => $query->orderBy('sale_price'), 'price_desc' => $query->orderByDesc('sale_price'), 'oldest' => $query->oldest(), default => $query->latest('published_at'),
        };
    }
}
