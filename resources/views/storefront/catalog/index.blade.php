@extends('layouts.storefront')
@section('title',$title.' | اتوکار')
@section('content')
<div class="container py-4">
    <nav class="breadcrumb-line" aria-label="breadcrumb"><a href="{{ route('home') }}">خانه</a>@foreach($breadcrumbs as $crumb)<span>/</span><span>{{ $crumb['name'] }}</span>@endforeach</nav>

    <div class="d-flex justify-content-between align-items-end gap-3 flex-wrap mb-4">
        <div><span class="eyebrow">فروشگاه قطعات خودرو</span><h1 class="h3 fw-black mb-1">{{ $title }}</h1><p class="text-muted small mb-0">{{ number_format($products->total()) }} کالا با امکان بررسی کد فنی و سازگاری خودرو</p></div>
        <a class="btn btn-outline-primary" href="{{ route('home') }}#vehicle-picker"><i class="bi bi-car-front"></i> انتخاب خودرو برای فیلتر دقیق‌تر</a>
    </div>

    <div class="catalog-layout">
        <aside class="filters ac-surface">
            <div class="d-flex align-items-center justify-content-between mb-3"><h5 class="mb-0">فیلتر محصولات</h5><i class="bi bi-sliders text-primary"></i></div>
            <form>
                <input type="hidden" name="q" value="{{ request('q') }}">
                <label class="form-label">برند قطعه</label>
                <select class="form-select" name="brand_id"><option value="">همه برندها</option>@foreach($brands as $brand)<option value="{{ $brand->id }}" @selected(request('brand_id')==$brand->id)>{{ $brand->name }}</option>@endforeach</select>
                <div class="mt-3"><label class="form-label">بازه قیمت</label><div class="row g-2"><div class="col"><input class="form-control" name="min_price" value="{{ request('min_price') }}" placeholder="از"></div><div class="col"><input class="form-control" name="max_price" value="{{ request('max_price') }}" placeholder="تا"></div></div></div>
                <button class="btn btn-primary w-100 mt-3"><i class="bi bi-funnel"></i> اعمال فیلترها</button>
                @if(request()->hasAny(['brand_id','min_price','max_price','sort']))<a class="btn btn-link w-100 mt-1 text-muted small" href="{{ request()->url().(request('q') ? '?q='.urlencode(request('q')) : '') }}">پاک کردن فیلترها</a>@endif
            </form>
            <hr class="my-4">
            <div class="small"><div class="d-flex gap-2 mb-2"><i class="bi bi-shield-check text-primary"></i><span>ضمانت اصالت کالا</span></div><div class="d-flex gap-2 mb-2"><i class="bi bi-arrow-counterclockwise text-primary"></i><span>مهلت بازگشت طبق شرایط کالا</span></div><div class="d-flex gap-2"><i class="bi bi-headset text-primary"></i><span>مشاوره انتخاب قطعه سازگار</span></div></div>
        </aside>

        <section class="catalog-content">
            <div class="catalog-toolbar ac-surface px-3 py-2 mb-3">
                <div><b>نمایش محصولات</b><span class="d-block">مرتب‌سازی و نتایج به‌روز</span></div>
                <form><input type="hidden" name="q" value="{{ request('q') }}">@if(request('brand_id'))<input type="hidden" name="brand_id" value="{{ request('brand_id') }}">@endif<select class="form-select" name="sort" onchange="this.form.submit()"><option value="">جدیدترین</option><option value="price_asc" @selected(request('sort')==='price_asc')>ارزان‌ترین</option><option value="price_desc" @selected(request('sort')==='price_desc')>گران‌ترین</option></select></form>
            </div>
            <div class="product-grid">@forelse($products as $product)@include('storefront.partials.product-card',['product'=>$product])@empty<div class="empty-state ac-surface"><i class="bi bi-search"></i><h3>محصولی پیدا نشد</h3><p>نام قطعه، SKU یا کد OEM دیگری را امتحان کنید.</p><a class="btn btn-primary" href="{{ route('search') }}">مشاهده همه محصولات</a></div>@endforelse</div>
            <div class="mt-4">{{ $products->links() }}</div>
        </section>
    </div>
</div>
@endsection
