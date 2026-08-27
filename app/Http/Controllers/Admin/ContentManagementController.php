<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Content\Models\Page;
use App\Domain\Content\Models\Post;
use App\Domain\Content\Services\ContentRevisionService;
use App\Domain\Content\Services\HtmlSanitizer;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ContentManagementController extends Controller
{
    /** Shows pages, posts and FAQs from one editorial workspace. */
    public function index(): View
    {
        return view('admin.content.manage', [
            'pages' => Page::query()->latest()->paginate(20, ['*'], 'pages_page'),
            'posts' => Post::query()->latest()->paginate(20, ['*'], 'posts_page'),
            'faqs' => DB::table('faqs')->orderBy('position')->get(),
        ]);
    }

    /** Creates a sanitized CMS page. */
    public function storePage(Request $request, HtmlSanitizer $sanitizer): RedirectResponse
    {
        $data = $this->pageData($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['body'] = $sanitizer->clean($data['body'] ?? null);
        Page::query()->create($data);

        return back()->with('success', 'صفحه ایجاد شد.');
    }

    /** Updates a page after preserving a full revision snapshot. */
    public function updatePage(Request $request, Page $page, HtmlSanitizer $sanitizer, ContentRevisionService $revisions): RedirectResponse
    {
        $data = $this->pageData($request, $page->id);
        $data['body'] = $sanitizer->clean($data['body'] ?? null);
        $revisions->snapshot($page, 'قبل از ویرایش صفحه');
        $page->update($data);

        return back()->with('success', 'صفحه ذخیره شد.');
    }

    /** Soft-deletes a page while preserving revisions. */
    public function destroyPage(Page $page, ContentRevisionService $revisions): RedirectResponse
    {
        $revisions->snapshot($page, 'قبل از حذف صفحه');
        $page->delete();

        return back()->with('success', 'صفحه حذف شد.');
    }

    /** Creates a sanitized blog post. */
    public function storePost(Request $request, HtmlSanitizer $sanitizer): RedirectResponse
    {
        $data = $this->postData($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['title']);
        $data['body'] = $sanitizer->clean($data['body']);
        $data['author_id'] = auth()->id();
        Post::query()->create($data);

        return back()->with('success', 'مقاله ایجاد شد.');
    }

    /** Updates a post and captures revision history. */
    public function updatePost(Request $request, Post $post, HtmlSanitizer $sanitizer, ContentRevisionService $revisions): RedirectResponse
    {
        $data = $this->postData($request, $post->id);
        $data['body'] = $sanitizer->clean($data['body']);
        $revisions->snapshot($post, 'قبل از ویرایش مقاله');
        $post->update($data);

        return back()->with('success', 'مقاله ذخیره شد.');
    }

    /** Soft-deletes a post after saving a revision. */
    public function destroyPost(Post $post, ContentRevisionService $revisions): RedirectResponse
    {
        $revisions->snapshot($post, 'قبل از حذف مقاله');
        $post->delete();

        return back()->with('success', 'مقاله حذف شد.');
    }

    /** Adds a FAQ entry. */
    public function storeFaq(Request $request): RedirectResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:500'], 'answer' => ['required', 'string', 'max:5000'], 'group' => ['nullable', 'string', 'max:100'], 'position' => ['nullable', 'integer', 'min:0']]);
        DB::table('faqs')->insert($data + ['position' => $data['position'] ?? 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'پرسش متداول ایجاد شد.');
    }

    /** Updates a FAQ entry. */
    public function updateFaq(Request $request, int $faq): RedirectResponse
    {
        $data = $request->validate(['question' => ['required', 'string', 'max:500'], 'answer' => ['required', 'string', 'max:5000'], 'group' => ['nullable', 'string', 'max:100'], 'position' => ['nullable', 'integer', 'min:0'], 'is_active' => ['nullable', 'boolean']]);
        DB::table('faqs')->where('id', $faq)->update($data + ['is_active' => $data['is_active'] ?? false, 'updated_at' => now()]);

        return back()->with('success', 'FAQ ذخیره شد.');
    }

    /** Shows revisions for one page or post. */
    public function revisions(string $type, string $slug, ContentRevisionService $revisions): View
    {
        $model = $this->contentModel($type, $slug);

        return view('admin.content.revisions', ['model' => $model, 'type' => $type, 'revisions' => $revisions->history($model)]);
    }

    /** Restores a selected page/post revision. */
    public function restoreRevision(string $type, string $slug, int $revision, ContentRevisionService $revisions): RedirectResponse
    {
        $revisions->restore($this->contentModel($type, $slug), $revision);

        return back()->with('success', 'نسخه انتخاب‌شده بازیابی شد.');
    }

    /** Validates page fields including Jalali-normalized publish date. */
    private function pageData(Request $request, ?int $id = null): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:190'], 'slug' => ['nullable', 'string', 'max:190', Rule::unique('pages', 'slug')->ignore($id)], 'body' => ['nullable', 'string'], 'status' => ['required', Rule::in(['draft', 'published'])], 'template' => ['nullable', 'string', 'max:80'], 'meta_title' => ['nullable', 'string', 'max:190'], 'meta_description' => ['nullable', 'string', 'max:1000'], 'published_at' => ['nullable', 'date']]);
    }

    /** Validates article fields including Jalali-normalized publish date. */
    private function postData(Request $request, ?int $id = null): array
    {
        return $request->validate(['title' => ['required', 'string', 'max:190'], 'slug' => ['nullable', 'string', 'max:190', Rule::unique('posts', 'slug')->ignore($id)], 'excerpt' => ['nullable', 'string', 'max:1000'], 'body' => ['required', 'string'], 'status' => ['required', Rule::in(['draft', 'published'])], 'meta_title' => ['nullable', 'string', 'max:190'], 'meta_description' => ['nullable', 'string', 'max:1000'], 'published_at' => ['nullable', 'date']]);
    }

    /** Resolves a revision-capable content model by type and slug. */
    private function contentModel(string $type, string $slug): Page|Post
    {
        return match ($type) {
            'page' => Page::query()->where('slug', $slug)->firstOrFail(),
            'post' => Post::query()->where('slug', $slug)->firstOrFail(),
            default => abort(404),
        };
    }
}
