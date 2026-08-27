<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Services\CategoryTreeService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CategoryController extends Controller
{
    /** Shows the unlimited-depth category tree and slug-addressable parent choices. */
    public function index(CategoryTreeService $service): View
    {
        return view('admin.categories.index', [
            'tree' => $service->tree(),
            'categories' => Category::query()->orderBy('depth')->orderBy('position')->orderBy('name')->get(),
        ]);
    }

    /** Creates a category using parent_slug rather than a public numeric parent id. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', 'unique:categories,slug'],
            'parent_slug' => ['nullable', 'string', 'max:190', 'exists:categories,slug'],
            'position' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['nullable', 'boolean'],
        ]);
        $parent = ! empty($data['parent_slug']) ? Category::query()->where('slug', $data['parent_slug'])->firstOrFail() : null;
        unset($data['parent_slug']);
        $data['parent_id'] = $parent?->id;
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $data['depth'] = $parent ? $parent->depth + 1 : 0;
        Category::query()->create($data);

        return back()->with('success', 'دسته‌بندی ایجاد شد.');
    }

    /** Moves a slug-bound category under a parent addressed by slug. */
    public function move(Request $request, Category $category, CategoryTreeService $service): RedirectResponse
    {
        $data = $request->validate([
            'parent_slug' => ['nullable', 'string', 'max:190', 'exists:categories,slug'],
            'position' => ['required', 'integer', 'min:0'],
        ]);
        $parent = ! empty($data['parent_slug']) ? Category::query()->where('slug', $data['parent_slug'])->firstOrFail() : null;
        $service->move($category, $parent, (int) $data['position']);

        return back()->with('success', 'جایگاه دسته‌بندی تغییر کرد.');
    }
}
