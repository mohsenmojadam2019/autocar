<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\Models\Category;
use App\Domain\Catalog\Models\Product;
use App\Domain\Promotion\Models\Coupon;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PromotionController extends Controller
{
    /** Shows coupon rules, usage and currently scoped product/category slugs. */
    public function index(): View
    {
        return view('admin.promotions.index', [
            'coupons' => Coupon::query()->latest()->paginate(30),
            'products' => Product::query()->orderBy('name')->limit(250)->get(['slug', 'name', 'sku']),
            'categories' => Category::query()->orderBy('depth')->orderBy('name')->get(['slug', 'name', 'depth']),
        ]);
    }

    /** Creates fixed/percentage/free-shipping/BOGO-compatible rule metadata. */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'unique:coupons,code'],
            'type' => ['required', 'in:fixed,percent,free_shipping,bogo'],
            'value' => ['required', 'numeric', 'min:0'],
            'max_discount' => ['nullable', 'integer', 'min:0'],
            'minimum_amount' => ['nullable', 'integer', 'min:0'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'per_user_limit' => ['nullable', 'integer', 'min:1'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'first_order_only' => ['nullable', 'boolean'],
            'stackable' => ['nullable', 'boolean'],
            'product_slugs' => ['nullable', 'array'],
            'product_slugs.*' => ['string', 'exists:products,slug'],
            'category_slugs' => ['nullable', 'array'],
            'category_slugs.*' => ['string', 'exists:categories,slug'],
        ]);
        $productSlugs = $data['product_slugs'] ?? [];
        $categorySlugs = $data['category_slugs'] ?? [];
        unset($data['product_slugs'], $data['category_slugs']);
        $coupon = Coupon::query()->create($data + ['is_active' => true, 'conditions' => []]);
        $coupon->newQuery()->find($coupon->id);
        DB::table('coupon_product')->insertOrIgnore(Product::query()->whereIn('slug', $productSlugs)->pluck('id')->map(fn ($id) => ['coupon_id' => $coupon->id, 'product_id' => $id])->all());
        DB::table('coupon_category')->insertOrIgnore(Category::query()->whereIn('slug', $categorySlugs)->pluck('id')->map(fn ($id) => ['coupon_id' => $coupon->id, 'category_id' => $id])->all());
        return back()->with('success', 'کوپن ایجاد شد.');
    }

    /** Enables or disables a coupon without deleting usage history. */
    public function toggle(Coupon $coupon): RedirectResponse
    {
        $coupon->update(['is_active' => ! $coupon->is_active]);
        return back()->with('success', 'وضعیت کوپن تغییر کرد.');
    }
}
