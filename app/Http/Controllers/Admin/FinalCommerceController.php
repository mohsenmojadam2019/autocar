<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Models\Attribute;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Catalog\Models\ProductBundle;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FinalCommerceController extends Controller
{
    public function index(): View
    {
        return view('admin.final-commerce.index', [
            'bundles' => ProductBundle::query()->with('products')->latest()->paginate(20),
            'tags' => DB::table('customer_tags')->orderBy('name')->get(),
            'suppressions' => DB::table('marketing_suppressions')->latest()->limit(100)->get(),
            'categories' => Category::query()->orderBy('position')->get(),
            'attributes' => Attribute::query()->orderBy('name')->get(),
        ]);
    }

    public function bundle(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:190'], 'slug' => ['nullable', 'string', 'max:190', 'unique:product_bundles,slug'], 'discount_type' => ['required', Rule::in(['percentage', 'fixed'])], 'discount_value' => ['required', 'numeric', 'min:0'], 'starts_at' => ['nullable', 'date'], 'ends_at' => ['nullable', 'date', 'after:starts_at'], 'products' => ['required', 'array', 'min:2'], 'products.*.slug' => ['required', 'exists:products,slug'], 'products.*.quantity' => ['required', 'integer', 'min:1', 'max:100']]);
        $bundle = ProductBundle::query()->create(['name' => $data['name'], 'slug' => $data['slug'] ?: Str::slug($data['name']).'-'.Str::lower(Str::random(5)), 'discount_type' => $data['discount_type'], 'discount_value' => $data['discount_value'], 'starts_at' => $data['starts_at'] ?? null, 'ends_at' => $data['ends_at'] ?? null, 'is_active' => true]);
        foreach ($data['products'] as $position => $item) {
            $product = Product::query()->where('slug', $item['slug'])->firstOrFail();
            $bundle->products()->attach($product->id, ['quantity' => $item['quantity'], 'position' => $position]);
        }

        return back()->with('success', 'باندل محصول ایجاد شد.');
    }

    public function categoryTemplate(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate(['attributes' => ['nullable', 'array'], 'attributes.*.id' => ['required', 'exists:attributes,id'], 'attributes.*.required' => ['nullable', 'boolean']]);
        $sync = [];
        foreach ($data['attributes'] ?? [] as $position => $item) {
            $sync[$item['id']] = ['is_required' => $item['required'] ?? false, 'position' => $position];
        }
        $category->attributes()->sync($sync);

        return back()->with('success', 'Template مشخصات دسته ذخیره شد.');
    }

    public function tag(Request $request): RedirectResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100'], 'slug' => ['nullable', 'string', 'max:100', 'unique:customer_tags,slug'], 'color' => ['nullable', 'string', 'max:16']]);
        DB::table('customer_tags')->insert(['name' => $data['name'], 'slug' => $data['slug'] ?: Str::slug($data['name']).'-'.Str::lower(Str::random(4)), 'color' => $data['color'] ?? null, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'برچسب مشتری ایجاد شد.');
    }

    public function assignTag(Request $request, int $customer): RedirectResponse
    {
        $data = $request->validate(['tag_id' => ['required', 'exists:customer_tags,id']]);
        DB::table('customer_tag_user')->updateOrInsert(['customer_tag_id' => $data['tag_id'], 'user_id' => $customer], ['created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'برچسب به مشتری تخصیص یافت.');
    }

    public function suppression(Request $request): RedirectResponse
    {
        $data = $request->validate(['channel' => ['required', Rule::in(['sms', 'email'])], 'value' => ['required', 'string', 'max:190'], 'reason' => ['nullable', 'string', 'max:190']]);
        DB::table('marketing_suppressions')->updateOrInsert(['channel' => $data['channel'], 'value' => $data['value']], ['reason' => $data['reason'] ?? null, 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);

        return back()->with('success', 'مخاطب به لیست عدم ارسال اضافه شد.');
    }

    /** Saves a complete drag-order payload by category slug, including optional parent changes. */
    public function reorder(Request $request): RedirectResponse
    {
        $data = $request->validate(['items' => ['required', 'array', 'min:1'], 'items.*.slug' => ['required', 'exists:categories,slug'], 'items.*.parent_slug' => ['nullable', 'exists:categories,slug'], 'items.*.position' => ['required', 'integer', 'min:0']]);
        DB::transaction(function () use ($data): void {
            foreach ($data['items'] as $item) {
                $parentId = ! empty($item['parent_slug']) ? Category::query()->where('slug', $item['parent_slug'])->value('id') : null;
                $category = Category::query()->where('slug', $item['slug'])->lockForUpdate()->firstOrFail();
                abort_if($parentId && (int) $parentId === (int) $category->id, 422, 'دسته نمی‌تواند والد خودش باشد.');
                $category->update(['parent_id' => $parentId, 'position' => $item['position']]);
            }
        });

        return back()->with('success', 'ترتیب دسته‌بندی‌ها ذخیره شد.');
    }
}
