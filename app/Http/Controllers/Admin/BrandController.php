<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Models\Brand;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BrandController extends Controller
{
    /** Lists part brands with product counts and status filters. */
    public function index(Request $request): View
    {
        $brands = Brand::query()->withCount('products')
            ->when($request->filled('q'), fn ($query) => $query->where('name', 'like', '%'.$request->q.'%')->orWhere('name_en', 'like', '%'.$request->q.'%'))
            ->orderBy('name')->paginate(30)->withQueryString();
        return view('admin.brands.index', compact('brands'));
    }

    /** Creates a SEO-ready brand using slug as its external identifier. */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name_en'] ?: $data['name']);
        Brand::query()->create($data);
        return back()->with('success', 'برند ایجاد شد.');
    }

    /** Updates a slug-bound brand. */
    public function update(Request $request, Brand $brand): RedirectResponse
    {
        $brand->update($this->validated($request, $brand->id));
        return back()->with('success', 'برند ذخیره شد.');
    }

    /** Soft-deletes a brand while preserving historical products. */
    public function destroy(Brand $brand): RedirectResponse
    {
        $brand->delete();
        return back()->with('success', 'برند حذف شد.');
    }

    /** Centralizes create/update validation for brand metadata. */
    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'name_en' => ['nullable', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('brands', 'slug')->ignore($id)],
            'country_code' => ['nullable', 'string', 'size:2'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
        ]);
    }
}
