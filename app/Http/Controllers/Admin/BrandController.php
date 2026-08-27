<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Models\Brand;
use App\Http\Controllers\Controller;
use App\Models\MediaAsset;
use App\Services\Media\LocalMediaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BrandController extends Controller
{
    /** Lists part brands with product counts and status filters. */
    public function index(Request $request): View
    {
        $brands = Brand::query()->withCount('products')
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($search) => $search
                ->where('name', 'like', '%'.$request->q.'%')
                ->orWhere('name_en', 'like', '%'.$request->q.'%')))
            ->orderBy('name')->paginate(30)->withQueryString();

        return view('admin.brands.index', compact('brands'));
    }

    /** Creates a SEO-ready brand with optional local logo using slug as its external identifier. */
    public function store(Request $request, LocalMediaService $media): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name_en'] ?: $data['name']);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        if ($request->hasFile('logo')) {
            $asset = $media->storeImage($request->file('logo'), 'brands');
            $data['logo_path'] = $asset->variant_path ?: $asset->path;
        }
        unset($data['logo']);
        Brand::query()->create($data);

        return back()->with('success', 'برند ایجاد شد.');
    }

    /** Updates a slug-bound brand and replaces its local logo only when a new file is provided. */
    public function update(Request $request, Brand $brand, LocalMediaService $media): RedirectResponse
    {
        $data = $this->validated($request, $brand->id);
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        if ($request->hasFile('logo')) {
            $asset = $media->storeImage($request->file('logo'), 'brands');
            $newPath = $asset->variant_path ?: $asset->path;
            if ($brand->logo_path) {
                $this->deleteManagedImage($brand->logo_path, $media);
            }
            $data['logo_path'] = $newPath;
        }
        unset($data['logo']);
        $brand->update($data);

        return back()->with('success', 'برند ذخیره شد.');
    }

    /** Soft-deletes a brand while preserving historical products and removes its owned logo. */
    public function destroy(Brand $brand, LocalMediaService $media): RedirectResponse
    {
        if ($brand->logo_path) {
            $this->deleteManagedImage($brand->logo_path, $media);
        }
        $brand->delete();

        return back()->with('success', 'برند حذف شد.');
    }

    /** Centralizes create/update validation for brand metadata and local logo uploads. */
    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'name_en' => ['nullable', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('brands', 'slug')->ignore($id)],
            'country_code' => ['nullable', 'string', 'size:2'],
            'logo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
            'description' => ['nullable', 'string'],
            'is_active' => ['nullable', 'boolean'],
            'meta_title' => ['nullable', 'string', 'max:190'],
            'meta_description' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    /** Removes a managed brand image through metadata or public-disk fallback. */
    private function deleteManagedImage(string $path, LocalMediaService $media): void
    {
        $asset = MediaAsset::query()->where('path', $path)->orWhere('variant_path', $path)->first();
        if ($asset) {
            $media->delete($asset);
            return;
        }
        Storage::disk('public')->delete($path);
    }
}
