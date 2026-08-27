<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Enums\AuthenticityType;
use App\Domain\Catalog\Enums\ProductStatus;
use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Services\ProductCloneService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductController extends Controller
{
    /** Lists products server-side with bounded search and status filters. */
    public function index(Request $request): View
    {
        $query = Product::query()->with('brand')
            ->when($request->filled('q'), fn ($builder) => $builder->where(fn ($search) => $search
                ->where('name', 'like', '%'.$request->q.'%')->orWhere('sku', 'like', '%'.$request->q.'%')->orWhere('oem_code', 'like', '%'.$request->q.'%')))
            ->when($request->filled('status'), fn ($builder) => $builder->where('status', $request->status))->latest();

        return view('admin.products.index', ['products' => $query->paginate(30)->withQueryString()]);
    }

    /** Shows the create form with reusable taxonomy data. */
    public function create(): View
    {
        return view('admin.products.form', ['product' => new Product(), 'brands' => Brand::query()->orderBy('name')->get(), 'categories' => Category::query()->orderBy('name')->get()]);
    }

    /** Stores a product and resolves category membership exclusively from category slugs. */
    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $categorySlugs = $data['categories'] ?? [];
        unset($data['categories']);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $product = Product::query()->create($data);
        $this->syncCategoriesBySlug($product, $categorySlugs);

        return redirect()->route('admin.products.edit', $product)->with('success', 'محصول ایجاد شد.');
    }

    /** Shows one slug-bound product edit form. */
    public function edit(Product $product): View
    {
        $product->load('categories');
        return view('admin.products.form', ['product' => $product, 'brands' => Brand::query()->orderBy('name')->get(), 'categories' => Category::query()->orderBy('name')->get()]);
    }

    /** Updates a slug-bound product and category membership. */
    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $this->validated($request, $product->id);
        $categorySlugs = $data['categories'] ?? [];
        unset($data['categories']);
        $product->update($data);
        $this->syncCategoriesBySlug($product, $categorySlugs);

        return back()->with('success', 'محصول ذخیره شد.');
    }

    /** Deep-clones a complete slug-bound product into draft state. */
    public function duplicate(Product $product, ProductCloneService $cloner): RedirectResponse
    {
        $copy = $cloner->clone($product);
        return redirect()->route('admin.products.edit', $copy)->with('success', 'کپی محصول ساخته شد.');
    }

    /** Resolves category slugs to internal foreign keys only inside the persistence boundary. */
    private function syncCategoriesBySlug(Product $product, array $slugs): void
    {
        $ids = Category::query()->whereIn('slug', array_values(array_unique($slugs)))->pluck('id')->all();
        $product->categories()->sync($ids);
    }

    /** Centralizes product validation shared by create and update operations. */
    private function validated(Request $request, ?int $id = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'name_en' => ['nullable', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('products', 'slug')->ignore($id)],
            'sku' => ['required', 'string', 'max:100', Rule::unique('products', 'sku')->ignore($id)],
            'oem_code' => ['nullable', 'string', 'max:100'],
            'manufacturer_code' => ['nullable', 'string', 'max:100'],
            'brand_id' => ['nullable', 'exists:brands,id'],
            'authenticity' => ['required', Rule::enum(AuthenticityType::class)],
            'status' => ['required', Rule::enum(ProductStatus::class)],
            'summary' => ['nullable', 'string', 'max:1000'],
            'description' => ['nullable', 'string'],
            'warranty' => ['nullable', 'string', 'max:190'],
            'return_days' => ['required', 'integer', 'min:0', 'max:365'],
            'sale_price' => ['required', 'integer', 'min:0'],
            'purchase_price' => ['nullable', 'integer', 'min:0'],
            'compare_at_price' => ['nullable', 'integer', 'min:0'],
            'wholesale_price' => ['nullable', 'integer', 'min:0'],
            'categories' => ['nullable', 'array'],
            'categories.*' => ['string', 'max:190', 'distinct', 'exists:categories,slug'],
        ]);
    }
}
