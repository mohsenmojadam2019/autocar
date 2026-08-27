@extends('layouts.admin')

@section('title', 'تخفیف‌ها | اتوکار')
@section('page_title', 'تخفیف، کوپن و پیشنهاد زمان‌دار')

@section('content')
<div class="row g-4">
    <div class="col-12">
        <div class="admin-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h4 class="mb-1">تخفیف خودکار زمان‌دار</h4>
                    <small class="text-muted">قابل اعمال روی کل فروشگاه، محصول، دسته یا برند؛ بدون نیاز به واردکردن کد.</small>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead><tr><th>نام</th><th>نوع</th><th>مقدار</th><th>Scope</th><th>بازه</th><th>وضعیت</th><th></th></tr></thead>
                    <tbody>
                    @forelse($automaticPromotions as $promotion)
                        <tr>
                            <td><b>{{ $promotion->name }}</b><br><code>{{ $promotion->slug }}</code></td>
                            <td>{{ $promotion->discount_type }}</td>
                            <td>{{ number_format((float) $promotion->discount_value, 0) }}</td>
                            <td>
                                @if($promotion->products_count + $promotion->categories_count + $promotion->brands_count === 0)
                                    <span class="status-badge">کل فروشگاه</span>
                                @else
                                    <small>محصول {{ $promotion->products_count }} · دسته {{ $promotion->categories_count }} · برند {{ $promotion->brands_count }}</small>
                                @endif
                            </td>
                            <td><small>{{ $promotion->starts_at?->format('Y-m-d H:i') ?: 'فوری' }}<br>تا {{ $promotion->ends_at?->format('Y-m-d H:i') ?: 'بدون پایان' }}</small></td>
                            <td>{{ $promotion->is_active ? 'فعال' : 'غیرفعال' }}</td>
                            <td class="text-nowrap">
                                <form class="d-inline" method="post" action="{{ route('admin.promotions.automatic.toggle', $promotion) }}">@csrf @method('patch')<button class="btn btn-sm btn-outline-secondary">تغییر وضعیت</button></form>
                                <form class="d-inline" method="post" action="{{ route('admin.promotions.automatic.destroy', $promotion) }}" onsubmit="return confirm('این تخفیف حذف شود؟')">@csrf @method('delete')<button class="btn btn-sm btn-outline-danger">حذف</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-muted py-4">تخفیف زمان‌دار ثبت نشده است.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
            {{ $automaticPromotions->links() }}
        </div>
    </div>

    <div class="col-xl-6">
        <div class="admin-card h-100">
            <h4>ساخت تخفیف زمان‌دار</h4>
            <form method="post" action="{{ route('admin.promotions.automatic.store') }}">
                @csrf
                <div class="row g-2">
                    <div class="col-md-8"><label>نام کمپین</label><input class="form-control" name="name" required placeholder="فروش ویژه آخر هفته"></div>
                    <div class="col-md-4"><label>اولویت</label><input class="form-control" type="number" name="priority" value="0" min="0"></div>
                    <div class="col-md-6"><label>نوع تخفیف</label><select class="form-select" name="discount_type"><option value="percentage">درصدی</option><option value="fixed">مبلغ ثابت</option><option value="sale_price">قیمت نهایی</option></select></div>
                    <div class="col-md-6"><label>مقدار</label><input class="form-control" type="number" name="discount_value" min="0" required></div>
                    <div class="col-md-6"><label>شروع</label><input class="form-control" type="datetime-local" name="starts_at"></div>
                    <div class="col-md-6"><label>پایان</label><input class="form-control" type="datetime-local" name="ends_at"></div>
                    <div class="col-md-6"><label>متن Badge</label><input class="form-control" name="badge_text" placeholder="پیشنهاد ویژه"></div>
                    <div class="col-md-3"><label>حداقل تعداد</label><input class="form-control" type="number" name="minimum_quantity" value="1" min="1"></div>
                    <div class="col-md-3"><label>حداکثر تعداد</label><input class="form-control" type="number" name="maximum_quantity" min="1"></div>
                    <div class="col-12"><label>محصولات (اختیاری)</label><select class="form-select" name="product_slugs[]" multiple size="5">@foreach($products as $product)<option value="{{ $product->slug }}">{{ $product->name }} — {{ $product->sku }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label>دسته‌ها (اختیاری)</label><select class="form-select" name="category_slugs[]" multiple size="5">@foreach($categories as $category)<option value="{{ $category->slug }}">{{ str_repeat('— ', (int) $category->depth) }}{{ $category->name }}</option>@endforeach</select></div>
                    <div class="col-md-6"><label>برندها (اختیاری)</label><select class="form-select" name="brand_slugs[]" multiple size="5">@foreach($brands as $brand)<option value="{{ $brand->slug }}">{{ $brand->name }}</option>@endforeach</select></div>
                </div>
                <div class="alert alert-light border mt-3 mb-2"><small>اگر محصول، دسته و برند انتخاب نشود، تخفیف روی کل محصولات منتشرشده اعمال می‌شود.</small></div>
                <button class="btn btn-primary w-100">ایجاد تخفیف زمان‌دار</button>
            </form>
        </div>
    </div>

    <div class="col-xl-6">
        <div class="admin-card h-100">
            <h4>کوپن جدید</h4>
            <form method="post" action="{{ route('admin.promotions.store') }}">
                @csrf
                <div class="row g-2">
                    <div class="col-md-6"><label>کد</label><input class="form-control" name="code" required placeholder="AUTOCAR10"></div>
                    <div class="col-md-6"><label>عنوان</label><input class="form-control" name="name" placeholder="خوش‌آمدگویی"></div>
                    <div class="col-md-6"><label>نوع</label><select class="form-select" name="type"><option value="percentage">درصدی</option><option value="fixed">مبلغ ثابت</option><option value="free_shipping">ارسال رایگان</option><option value="bogo">X بخر Y بگیر</option></select></div>
                    <div class="col-md-6"><label>مقدار</label><input class="form-control" type="number" name="value" value="0" min="0" required></div>
                    <div class="col-md-6"><label>سقف تخفیف</label><input class="form-control" type="number" name="max_discount" min="0"></div>
                    <div class="col-md-6"><label>حداقل سبد</label><input class="form-control" type="number" name="minimum_subtotal" min="0"></div>
                    <div class="col-md-6"><label>شروع</label><input class="form-control" type="datetime-local" name="starts_at"></div>
                    <div class="col-md-6"><label>پایان</label><input class="form-control" type="datetime-local" name="ends_at"></div>
                    <div class="col-md-6"><label>سقف مصرف کل</label><input class="form-control" type="number" name="usage_limit" min="1"></div>
                    <div class="col-md-6"><label>سقف هر مشتری</label><input class="form-control" type="number" name="per_user_limit" min="1"></div>
                    <div class="col-12 d-flex gap-4 py-2"><label><input type="checkbox" name="first_order_only" value="1"> فقط سفارش اول</label><label><input type="checkbox" name="stackable" value="1"> قابل ترکیب</label></div>
                </div>
                <button class="btn btn-outline-primary w-100">ایجاد کوپن</button>
            </form>
        </div>
    </div>

    <div class="col-12">
        <div class="admin-card table-responsive">
            <h4>کوپن‌ها</h4>
            <table class="table align-middle">
                <thead><tr><th>کد</th><th>نوع</th><th>مقدار</th><th>مصرف</th><th>بازه</th><th>فعال</th><th></th></tr></thead>
                <tbody>
                @forelse($coupons as $coupon)
                    <tr>
                        <td><code>{{ $coupon->code }}</code></td><td>{{ $coupon->type }}</td><td>{{ $coupon->value }}</td>
                        <td>{{ $coupon->redemptions_count }}/{{ $coupon->usage_limit ?: '∞' }}</td>
                        <td><small>{{ $coupon->starts_at?->format('Y-m-d H:i') ?: 'فوری' }}<br>{{ $coupon->ends_at?->format('Y-m-d H:i') ?: 'بدون پایان' }}</small></td>
                        <td>{{ $coupon->is_active ? 'بله' : 'خیر' }}</td>
                        <td><form method="post" action="{{ route('admin.promotions.toggle', $coupon) }}">@csrf @method('patch')<button class="btn btn-sm btn-outline-secondary">تغییر وضعیت</button></form></td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-muted py-4">کوپنی ثبت نشده است.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $coupons->links() }}
        </div>
    </div>
</div>
@endsection
