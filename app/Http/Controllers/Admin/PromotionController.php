<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Models\Brand;
use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Promotion\Models\AutomaticPromotion;
use App\Domain\Promotion\Models\Coupon;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PromotionController extends Controller
{
    /** Shows coupon rules and automatic timed promotions with slug-based catalog scopes. */
    public function index(): View
    {
        return view('admin.promotions.index', [
            'coupons' => Coupon::query()->withCount('redemptions')->latest()->paginate(20, ['*'], 'coupon_page'),
            'automaticPromotions' => AutomaticPromotion::query()
                ->withCount(['products', 'categories', 'brands'])
                ->latest()
                ->paginate(20, ['*'], 'promotion_page'),
            'products' => Product::query()->orderBy('name')->limit(500)->get(['slug', 'name', 'sku']),
            'categories' => Category::query()->orderBy('depth')->orderBy('name')->get(['slug', 'name', 'depth']),
            'brands' => Brand::query()->orderBy('name')->get(['slug', 'name']),
        ]);
    }

    /** Creates a code-based coupon with product/category scope and advanced BOGO/free-shipping metadata. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:coupons,code'],
            'name' => ['nullable', 'string', 'max:190'],
            'type' => ['required', 'in:fixed,percentage,free_shipping,bogo'],
            'value' => ['required', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'integer', 'min:0'],
            'minimum_subtotal' => ['nullable', 'integer', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'first_order_only' => ['nullable', 'boolean'],
            'stackable' => ['nullable', 'boolean'],
            'buy_quantity' => ['nullable', 'integer', 'min:1'],
            'get_quantity' => ['nullable', 'integer', 'min:1'],
            'get_discount_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'product_slugs' => ['nullable', 'array'],
            'product_slugs.*' => ['string', 'exists:products,slug'],
            'category_slugs' => ['nullable', 'array'],
            'category_slugs.*' => ['string', 'exists:categories,slug'],
        ]);

        $productSlugs = $data['product_slugs'] ?? [];
        $categorySlugs = $data['category_slugs'] ?? [];
        $conditions = [
            'buy_quantity' => $data['buy_quantity'] ?? null,
            'get_quantity' => $data['get_quantity'] ?? null,
            'get_discount_percent' => $data['get_discount_percent'] ?? null,
        ];
        unset(
            $data['product_slugs'],
            $data['category_slugs'],
            $data['buy_quantity'],
            $data['get_quantity'],
            $data['get_discount_percent'],
        );

        DB::transaction(function () use ($data, $conditions, $productSlugs, $categorySlugs): void {
            $coupon = Coupon::query()->create(array_merge($data, [
                'code' => Str::upper(trim($data['code'])),
                'name' => $data['name'] ?: Str::upper(trim($data['code'])),
                'minimum_subtotal' => $data['minimum_subtotal'] ?? 0,
                'first_order_only' => (bool) ($data['first_order_only'] ?? false),
                'stackable' => (bool) ($data['stackable'] ?? false),
                'is_active' => true,
                'conditions' => array_filter($conditions, fn ($value) => $value !== null),
            ]));

            DB::table('coupon_product')->insertOrIgnore(
                Product::query()->whereIn('slug', $productSlugs)->pluck('id')
                    ->map(fn ($id) => ['coupon_id' => $coupon->id, 'product_id' => $id])
                    ->all(),
            );
            DB::table('coupon_category')->insertOrIgnore(
                Category::query()->whereIn('slug', $categorySlugs)->pluck('id')
                    ->map(fn ($id) => ['coupon_id' => $coupon->id, 'category_id' => $id])
                    ->all(),
            );
        });

        return back()->with('success', 'کوپن ایجاد شد.');
    }

    /** Creates an automatic date-window promotion scoped to products, categories, brands or globally. */
    public function storeAutomatic(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'slug' => ['nullable', 'string', 'max:190', Rule::unique('automatic_promotions', 'slug')],
            'discount_type' => ['required', 'in:percentage,fixed,sale_price'],
            'discount_value' => ['required', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'integer', 'min:0'],
            'minimum_quantity' => ['nullable', 'integer', 'min:1'],
            'maximum_quantity' => ['nullable', 'integer', 'gte:minimum_quantity'],
            'badge_text' => ['nullable', 'string', 'max:100'],
            'priority' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'product_slugs' => ['nullable', 'array'],
            'product_slugs.*' => ['string', 'exists:products,slug'],
            'category_slugs' => ['nullable', 'array'],
            'category_slugs.*' => ['string', 'exists:categories,slug'],
            'brand_slugs' => ['nullable', 'array'],
            'brand_slugs.*' => ['string', 'exists:brands,slug'],
        ]);

        DB::transaction(function () use ($data): void {
            $promotion = AutomaticPromotion::query()->create([
                'name' => $data['name'],
                'slug' => $data['slug'] ?: Str::slug($data['name']).'-'.Str::lower(Str::random(5)),
                'discount_type' => $data['discount_type'],
                'discount_value' => $data['discount_value'],
                'max_discount' => $data['max_discount'] ?? null,
                'minimum_quantity' => $data['minimum_quantity'] ?? 1,
                'maximum_quantity' => $data['maximum_quantity'] ?? null,
                'badge_text' => $data['badge_text'] ?? null,
                'priority' => $data['priority'] ?? 0,
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'is_active' => true,
                'stackable' => false,
                'conditions' => [],
            ]);

            $promotion->products()->sync(Product::query()->whereIn('slug', $data['product_slugs'] ?? [])->pluck('id'));
            $promotion->categories()->sync(Category::query()->whereIn('slug', $data['category_slugs'] ?? [])->pluck('id'));
            $promotion->brands()->sync(Brand::query()->whereIn('slug', $data['brand_slugs'] ?? [])->pluck('id'));
        });

        return back()->with('success', 'تخفیف خودکار زمان‌دار ایجاد شد.');
    }

    /** Enables or disables a coupon without deleting usage history. */
    public function toggle(Coupon $coupon): RedirectResponse
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);

        return back()->with('success', 'وضعیت کوپن تغییر کرد.');
    }

    /** Enables or disables an automatic promotion while preserving its history and scope. */
    public function toggleAutomatic(AutomaticPromotion $promotion): RedirectResponse
    {
        $promotion->update(['is_active' => ! $promotion->is_active]);

        return back()->with('success', 'وضعیت تخفیف زمان‌دار تغییر کرد.');
    }

    /** Deletes an unused automatic merchandising rule and its scope pivots. */
    public function destroyAutomatic(AutomaticPromotion $promotion): RedirectResponse
    {
        $promotion->delete();

        return back()->with('success', 'تخفیف زمان‌دار حذف شد.');
    }
}
