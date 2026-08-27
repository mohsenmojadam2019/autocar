<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ContentController extends Controller
{
    /** Renders a published CMS page addressed by slug. */
    public function page(string $slug): View
    {
        $page = DB::table('pages')->where('slug', $slug)->where('status', 'published')->whereNull('deleted_at')->where(fn ($query) => $query->whereNull('published_at')->orWhere('published_at', '<=', now()))->first();
        abort_unless($page, 404);
        return view('storefront.content.page', compact('page'));
    }

    /** Lists published articles for SEO-friendly educational content. */
    public function blog(): View
    {
        $posts = DB::table('posts')->where('status', 'published')->whereNull('deleted_at')->where('published_at', '<=', now())->latest('published_at')->paginate(12);
        return view('storefront.content.blog', compact('posts'));
    }

    /** Renders one published article by slug. */
    public function post(string $slug): View
    {
        $post = DB::table('posts')->where('slug', $slug)->where('status', 'published')->whereNull('deleted_at')->where('published_at', '<=', now())->first();
        abort_unless($post, 404);
        return view('storefront.content.post', compact('post'));
    }

    /** Shows grouped active frequently-asked questions. */
    public function faq(): View
    {
        $faqs = DB::table('faqs')->where('is_active', true)->orderBy('group')->orderBy('position')->get()->groupBy('group');
        return view('storefront.content.faq', compact('faqs'));
    }
}
