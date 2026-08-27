@extends('layouts.admin')

@section('title', 'بنرها | اتوکار')
@section('page_title', 'بنر، اسلایدر و جایگاه تبلیغاتی')

@section('content')
<div class="row g-4">
    <div class="col-xl-8">
        <div class="admin-card table-responsive">
            <div class="admin-heading"><div><h1>بنرها</h1><p>زمان‌بندی، نسخه موبایل، Placement و گزارش کلیک/نمایش</p></div></div>
            <table class="table align-middle">
                <thead><tr><th>پیش‌نمایش</th><th>نام / جایگاه</th><th>زمان</th><th>آمار</th><th>وضعیت</th><th></th></tr></thead>
                <tbody>
                @forelse($banners as $banner)
                    <tr>
                        <td><img class="banner-preview" src="{{ asset('storage/'.$banner->image_path) }}" alt=""></td>
                        <td><b>{{ $banner->name }}</b><br><code>{{ $banner->placement }}</code><br><small>{{ $banner->url }}</small></td>
                        <td><small>{{ $banner->starts_at?->format('Y-m-d H:i') ?: 'فوری' }}<br>تا {{ $banner->ends_at?->format('Y-m-d H:i') ?: 'بدون پایان' }}</small></td>
                        <td><small>نمایش: {{ number_format((int) $banner->impressions_count) }}<br>کلیک: {{ number_format((int) $banner->clicks_count) }}</small></td>
                        <td>{{ $banner->is_active ? 'فعال' : 'غیرفعال' }}</td>
                        <td class="text-nowrap"><form class="d-inline" method="post" action="{{ route('admin.banners.toggle', $banner) }}">@csrf @method('patch')<button class="btn btn-sm btn-outline-secondary">فعال/غیرفعال</button></form><form class="d-inline" method="post" action="{{ route('admin.banners.destroy', $banner) }}" onsubmit="return confirm('بنر حذف شود؟')">@csrf @method('delete')<button class="btn btn-sm btn-outline-danger">حذف</button></form></td>
                    </tr>
                    <tr><td colspan="6"><details><summary class="btn btn-sm btn-light">ویرایش {{ $banner->name }}</summary><form class="row g-2 mt-2" method="post" enctype="multipart/form-data" action="{{ route('admin.banners.update', $banner) }}">@csrf @method('put')<div class="col-md-4"><label>نام</label><input class="form-control" name="name" value="{{ $banner->name }}" required></div><div class="col-md-4"><label>Placement</label><input class="form-control" name="placement" value="{{ $banner->placement }}" required></div><div class="col-md-4"><label>ترتیب</label><input class="form-control" type="number" name="position" value="{{ $banner->position }}" min="0"></div><div class="col-md-6"><label>تصویر جدید</label><input class="form-control" type="file" name="image" accept="image/jpeg,image/png,image/webp"></div><div class="col-md-6"><label>موبایل جدید</label><input class="form-control" type="file" name="mobile_image" accept="image/jpeg,image/png,image/webp"></div><div class="col-md-6"><label>لینک</label><input class="form-control" name="url" value="{{ $banner->url }}"></div><div class="col-md-6"><label>Alt</label><input class="form-control" name="alt" value="{{ $banner->alt }}"></div><div class="col-md-6"><label>شروع</label><input class="form-control" type="datetime-local" name="starts_at" value="{{ $banner->starts_at?->format('Y-m-d\TH:i') }}"></div><div class="col-md-6"><label>پایان</label><input class="form-control" type="datetime-local" name="ends_at" value="{{ $banner->ends_at?->format('Y-m-d\TH:i') }}"></div><div class="col-12"><label><input type="checkbox" name="is_active" value="1" @checked($banner->is_active)> فعال</label></div><div class="col-12"><button class="btn btn-primary">ذخیره بنر</button></div></form></details></td></tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-5">بنری ثبت نشده است.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $banners->links() }}
        </div>
    </div>
    <div class="col-xl-4">
        <div class="admin-card sticky-top" style="top:90px">
            <h4>بنر جدید</h4>
            <form method="post" enctype="multipart/form-data" action="{{ route('admin.banners.store') }}">@csrf
                <label>نام</label><input class="form-control mb-2" name="name" required placeholder="Hero فروش ویژه">
                <label>Placement</label><select class="form-select mb-2" name="placement"><option value="home_hero">home_hero</option><option value="home_middle">home_middle</option><option value="home_bottom">home_bottom</option><option value="category_top">category_top</option><option value="product_sidebar">product_sidebar</option></select>
                <label>تصویر دسکتاپ</label><input class="form-control mb-2" type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
                <label>نسخه موبایل</label><input class="form-control mb-2" type="file" name="mobile_image" accept="image/jpeg,image/png,image/webp">
                <label>لینک</label><input class="form-control mb-2" name="url" placeholder="/category/oil-filter یا https://...">
                <label>Alt</label><input class="form-control mb-2" name="alt">
                <div class="row g-2"><div class="col-6"><label>شروع</label><input class="form-control" type="datetime-local" name="starts_at"></div><div class="col-6"><label>پایان</label><input class="form-control" type="datetime-local" name="ends_at"></div></div>
                <label class="mt-2">ترتیب</label><input class="form-control mb-2" type="number" name="position" value="0" min="0">
                <label class="d-flex gap-2 mb-3"><input type="checkbox" name="is_active" value="1" checked> فعال</label>
                <button class="btn btn-primary w-100">ساخت بنر</button>
            </form>
        </div>
    </div>
</div>
@endsection
