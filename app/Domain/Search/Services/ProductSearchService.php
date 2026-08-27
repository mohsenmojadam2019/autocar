<?php

namespace App\Domain\Search\Services;

use App\Domain\Catalog\Models\Product;
use App\Support\JalaliDate;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ProductSearchService
{
    /** Searches normalized Persian terms plus active synonyms and records optional search history. */
    public function search(string $term, array $filters = [], int $perPage = 24, ?int $userId = null, ?string $sessionToken = null): LengthAwarePaginator
    {
        $term = $this->normalize($term);
        $terms = $this->terms($term);
        $query = Product::query()->published()->with(['brand', 'media'])
            ->where(function (Builder $query) use ($terms): void {
                foreach ($terms as $index => $candidate) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}(function (Builder $nested) use ($candidate): void {
                        $like = '%'.$candidate.'%';
                        $nested->where('name', 'like', $like)->orWhere('name_en', 'like', $like)
                            ->orWhere('sku', 'like', $like)->orWhere('oem_code', 'like', $like)
                            ->orWhere('manufacturer_code', 'like', $like);
                    });
                }
            })
            ->when($filters['brand_slug'] ?? null, fn (Builder $q, string $slug) => $q->whereHas('brand', fn (Builder $b) => $b->where('slug', $slug)))
            ->when($filters['category_slug'] ?? null, fn (Builder $q, string $slug) => $q->whereHas('categories', fn (Builder $c) => $c->where('slug', $slug)))
            ->orderByRaw('CASE WHEN sku = ? OR oem_code = ? THEN 0 ELSE 1 END', [$term, $term])
            ->orderByDesc('published_at');
        $results = $query->paginate(min(max($perPage, 1), 60));
        if ($userId || $sessionToken) {
            DB::table('search_histories')->insert([
                'user_id' => $userId,
                'session_token' => $sessionToken,
                'term' => $term,
                'results_count' => $results->total(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return $results;
    }

    /** Returns autocomplete using synonyms and public slugs only. */
    public function suggest(string $term, int $limit = 8): array
    {
        $term = $this->normalize($term);
        if (mb_strlen($term) < 2) {
            return [];
        }
        $terms = $this->terms($term);

        return Product::query()->published()
            ->where(function (Builder $query) use ($terms): void {
                foreach ($terms as $index => $candidate) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}(fn (Builder $nested) => $nested->where('name', 'like', '%'.$candidate.'%')->orWhere('sku', 'like', '%'.$candidate.'%')->orWhere('oem_code', 'like', '%'.$candidate.'%'));
                }
            })
            ->limit(min($limit, 12))->get(['name', 'slug', 'sku', 'oem_code', 'sale_price'])->toArray();
    }

    /** Returns recent unique terms for the current customer/session. */
    public function history(?int $userId, ?string $sessionToken, int $limit = 10): array
    {
        return DB::table('search_histories')
            ->when($userId, fn ($query) => $query->where('user_id', $userId), fn ($query) => $query->where('session_token', $sessionToken))
            ->latest('id')->limit(min(max($limit, 1), 30))->pluck('term')->unique()->values()->all();
    }

    /** Expands a normalized term using both direction of active synonym mappings. */
    private function terms(string $term): array
    {
        $synonyms = DB::table('search_synonyms')->where('is_active', true)
            ->where(fn ($query) => $query->where('term', $term)->orWhere('synonym', $term))
            ->orderByDesc('weight')->get(['term', 'synonym']);
        $terms = [$term];
        foreach ($synonyms as $synonym) {
            $terms[] = $this->normalize($synonym->term);
            $terms[] = $this->normalize($synonym->synonym);
        }

        return array_values(array_unique(array_filter($terms)));
    }

    /** Normalizes common Arabic/Persian glyph and digit differences before matching. */
    private function normalize(string $value): string
    {
        $value = str_replace(['ي', 'ك', 'ة', 'ۀ'], ['ی', 'ک', 'ه', 'ه'], $value);
        $value = app(JalaliDate::class)->latinDigits($value);

        return trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
    }
}
