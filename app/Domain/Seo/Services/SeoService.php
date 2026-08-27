<?php

namespace App\Domain\Seo\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class SeoService
{
    /** Builds canonical metadata with safe fallbacks and no duplicate empty tags. */
    public function meta(string $title, ?string $description = null, ?string $canonical = null, array $extra = []): array
    {
        return array_filter(['title' => $title, 'description' => $description ? Str::limit(strip_tags($description), 160) : null, 'canonical' => $canonical, 'robots' => $extra['robots'] ?? 'index,follow', 'image' => $extra['image'] ?? null], fn ($v) => $v !== null && $v !== '');
    }

    /** Generates sitemap records for public products, categories, pages and posts. */
    public function sitemapRecords(): array
    {
        $records = [];
        foreach (DB::table('products')->where('status', 'active')->whereNull('deleted_at')->get(['slug', 'updated_at']) as $row) {
            $records[] = ['loc' => url('/product/'.$row->slug), 'lastmod' => $row->updated_at];
        } foreach (DB::table('categories')->where('is_active', true)->whereNull('deleted_at')->get(['slug', 'updated_at']) as $row) {
            $records[] = ['loc' => url('/category/'.$row->slug), 'lastmod' => $row->updated_at];
        } foreach (DB::table('pages')->where('status', 'published')->whereNull('deleted_at')->get(['slug', 'updated_at']) as $row) {
            $records[] = ['loc' => url('/page/'.$row->slug), 'lastmod' => $row->updated_at];
        }

        return $records;
    }
}
