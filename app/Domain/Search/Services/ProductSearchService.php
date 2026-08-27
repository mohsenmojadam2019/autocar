<?php

namespace App\Domain\Search\Services;

use App\Domain\Catalog\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductSearchService
{
    /** Searches Persian/English names, SKU, OEM and manufacturer codes with database fallback. */
    public function search(string $term, array $filters = [], int $perPage = 24): LengthAwarePaginator
    {
        $term = $this->normalize($term);
        return Product::query()->published()
            ->with(['brand', 'media'])
            ->where(function (Builder $query) use ($term): void {
                $like = '%'.$term.'%';
                $query->where('name', 'like', $like)->orWhere('name_en', 'like', $like)
                    ->orWhere('sku', 'like', $like)->orWhere('oem_code', 'like', $like)
                    ->orWhere('manufacturer_code', 'like', $like);
            })
            ->when($filters['brand_id'] ?? null, fn (Builder $q, $id) => $q->where('brand_id', $id))
            ->when($filters['category_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('categories', fn (Builder $c) => $c->whereKey($id)))
            ->orderByRaw('CASE WHEN sku = ? OR oem_code = ? THEN 0 ELSE 1 END', [$term, $term])
            ->orderByDesc('published_at')->paginate(min(max($perPage, 1), 60));
    }

    /** Returns lightweight autocomplete suggestions without exposing unpublished products. */
    public function suggest(string $term, int $limit = 8): array
    {
        $term = $this->normalize($term);
        if (mb_strlen($term) < 2) return [];
        return Product::query()->published()->where(fn (Builder $q) => $q->where('name', 'like', '%'.$term.'%')->orWhere('sku', 'like', '%'.$term.'%')->orWhere('oem_code', 'like', '%'.$term.'%'))
            ->limit(min($limit, 12))->get(['id','name','slug','sku','oem_code','sale_price'])->toArray();
    }

    /** Normalizes common Arabic/Persian glyph differences before matching. */
    private function normalize(string $value): string
    {
        return trim(str_replace(['ي','ك','ة'], ['ی','ک','ه'], $value));
    }
}
