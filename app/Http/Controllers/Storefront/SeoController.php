<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SeoController extends Controller
{
    /** Generates a deterministic XML sitemap from published products/categories/content. */
    public function sitemap(): Response
    {
        $urls = collect([['loc' => route('home'), 'lastmod' => now()]])
            ->merge(Product::query()->published()->get(['slug', 'updated_at'])->map(fn ($item) => ['loc' => route('product.show', $item), 'lastmod' => $item->updated_at]))
            ->merge(Category::query()->visible()->get(['slug', 'updated_at'])->map(fn ($item) => ['loc' => route('category.show', $item), 'lastmod' => $item->updated_at]))
            ->merge(DB::table('pages')->where('status', 'published')->whereNull('deleted_at')->get(['slug', 'updated_at'])->map(fn ($item) => ['loc' => route('content.page', $item->slug), 'lastmod' => $item->updated_at]))
            ->merge(DB::table('posts')->where('status', 'published')->whereNull('deleted_at')->get(['slug', 'updated_at'])->map(fn ($item) => ['loc' => route('blog.show', $item->slug), 'lastmod' => $item->updated_at]));
        $xml = view('seo.sitemap', compact('urls'))->render();

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    /** Emits crawler policy and points to the canonical sitemap. */
    public function robots(): Response
    {
        return response("User-agent: *\nAllow: /\nDisallow: /admin\nDisallow: /account\nDisallow: /checkout\nSitemap: ".route('sitemap')."\n", 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
