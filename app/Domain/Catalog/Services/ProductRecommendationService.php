<?php

namespace App\Domain\Catalog\Services;

use App\Domain\Catalog\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductRecommendationService
{
    /** Returns curated similar products first, then safely fills remaining slots from shared categories and brand. */
    public function similar(Product $product, int $limit = 8): Collection
    {
        $curated = $product->relatedProducts()
            ->published()
            ->with(['brand', 'media'])
            ->limit($limit)
            ->get();

        return $this->fillFallback($product, $curated, $limit);
    }

    /** Returns explicitly curated complementary products for cross-sell blocks and cart suggestions. */
    public function complementary(Product $product, int $limit = 8): Collection
    {
        return $product->complementaryProducts()
            ->published()
            ->with(['brand', 'media'])
            ->limit($limit)
            ->get();
    }

    /** Returns explicitly curated compatible alternatives, useful when stock or price differs. */
    public function alternatives(Product $product, int $limit = 8): Collection
    {
        return $product->alternativeProducts()
            ->published()
            ->with(['brand', 'media'])
            ->limit($limit)
            ->get();
    }

    /** Returns explicitly curated upsell products intended to increase order value without replacing fitment validation. */
    public function upsells(Product $product, int $limit = 8): Collection
    {
        return $product->upsellProducts()
            ->published()
            ->with(['brand', 'media'])
            ->limit($limit)
            ->get();
    }

    /** Completes a curated list with same-category/brand products while preventing duplicates and self references. */
    private function fillFallback(Product $product, Collection $curated, int $limit): Collection
    {
        if ($curated->count() >= $limit) {
            return $curated->take($limit)->values();
        }

        $categoryIds = $product->categories()->pluck('categories.id')->all();
        $excluded = $curated->pluck('id')->push($product->id)->all();
        $fallback = Product::query()
            ->published()
            ->whereNotIn('id', $excluded)
            ->where(function (Builder $query) use ($product, $categoryIds): void {
                if ($categoryIds !== []) {
                    $query->whereHas('categories', fn (Builder $category) => $category->whereKey($categoryIds));
                }
                if ($product->brand_id) {
                    $method = $categoryIds !== [] ? 'orWhere' : 'where';
                    $query->{$method}('brand_id', $product->brand_id);
                }
            })
            ->with(['brand', 'media'])
            ->latest('published_at')
            ->limit($limit - $curated->count())
            ->get();

        return $curated->concat($fallback)->take($limit)->values();
    }
}
