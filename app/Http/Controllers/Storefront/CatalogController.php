<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Search\Services\ProductSearchService;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CatalogController extends Controller
{
    /** Lists a slug-bound category with safe brand/price filters and stable pagination. */
    public function category(Request $request, Category $category): View
    {
        abort_unless($category->is_active, 404);
        $query = $category->products()->published()->with(['brand', 'media']);
        $this->applyFilters($query, $request);

        return view('storefront.catalog.index', [
            'title' => $category->name,
            'category' => $category,
            'products' => $query->paginate(24)->withQueryString(),
            'brands' => Brand::query()->visible()->orderBy('name')->get(),
            'breadcrumbs' => $category->breadcrumb(),
        ]);
    }

    /** Renders search results using slug filters rather than public numeric identifiers. */
    public function search(Request $request, ProductSearchService $search): View
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'brand_slug' => ['nullable', 'string', 'max:190', 'exists:brands,slug'],
            'category_slug' => ['nullable', 'string', 'max:190', 'exists:categories,slug'],
        ]);
        $term = trim($data['q'] ?? '');
        $products = $term !== ''
            ? $search->search($term, $data)
            : Product::query()->published()->with(['brand', 'media'])
                ->when($data['brand_slug'] ?? null, fn (Builder $q, string $slug) => $q->whereHas('brand', fn (Builder $b) => $b->where('slug', $slug)))
                ->when($data['category_slug'] ?? null, fn (Builder $q, string $slug) => $q->whereHas('categories', fn (Builder $c) => $c->where('slug', $slug)))
                ->latest('published_at')->paginate(24);

        return view('storefront.catalog.index', [
            'title' => $term !== '' ? 'نتایج جست‌وجوی «'.$term.'»' : 'همه قطعات',
            'products' => $products,
            'brands' => Brand::query()->visible()->orderBy('name')->get(),
            'breadcrumbs' => [],
            'category' => null,
        ]);
    }

    /** Shows a slug-bound published product with media, specifications and fitment data. */
    public function product(Product $product): View
    {
        abort_unless(Product::query()->published()->whereKey($product->getKey())->exists(), 404);
        $product->load(['brand', 'media', 'categories', 'variants', 'attributeValues.attribute', 'attributeValues.option', 'fitments']);
        $related = Product::query()->published()->whereKeyNot($product->id)
            ->when($product->brand_id, fn ($query) => $query->where('brand_id', $product->brand_id))
            ->with('media')->limit(4)->get();

        return view('storefront.product.show', compact('product', 'related'));
    }

    /** Applies safe catalog filters without allowing arbitrary client-supplied columns. */
    private function applyFilters(Builder|BelongsToMany $query, Request $request): void
    {
        $query->when($request->string('brand_slug')->toString(), fn ($builder, string $slug) => $builder->whereHas('brand', fn ($brand) => $brand->where('slug', $slug)))
            ->when($request->integer('min_price'), fn ($builder, int $value) => $builder->where('sale_price', '>=', $value))
            ->when($request->integer('max_price'), fn ($builder, int $value) => $builder->where('sale_price', '<=', $value));

        match ($request->string('sort')->toString()) {
            'price_asc' => $query->orderBy('sale_price'),
            'price_desc' => $query->orderByDesc('sale_price'),
            'oldest' => $query->oldest(),
            default => $query->latest('published_at'),
        };
    }
}
