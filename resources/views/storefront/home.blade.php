@extends('layouts.storefront')
@section('title','اتوکار | فروشگاه تخصصی قطعات و لوازم یدکی')
@section('content')
@php
    $categoryIcons = ['bi-car-front','bi-fan','bi-disc','bi-battery-charging','bi-lightning-charge','bi-fuel-pump','bi-wrench-adjustable','bi-tools','bi-droplet-half','bi-grid'];
    $featuredDeal = $specialProducts->first();
    $heroBanner = $heroBanners->first();
@endphp

<section class="reference-home-hero">
    <div class="container">
        <div class="reference-hero-shell">
            <div class="reference-hero-copy">
                <span class="reference-kicker">فروشگاه تخصصی قطعات خودرو</span>
                <h1>قطعه درست،<br><strong>دقیقاً برای خودروی شما</strong></h1>
                <p>با انتخاب خودروی خود، فقط قطعات سازگار را ببینید؛ یا با نام قطعه، برند، SKU و کد OEM مستقیم جستجو کنید.</p>
                <div class="reference-hero-cta">
                    <a class="btn btn-danger" href="#vehicle-picker">انتخاب خودرو</a>
                    <a class="btn btn-outline-dark" href="{{ route('search') }}">مشاهده همه قطعات</a>
                </div>
            </div>

            <div class="reference-hero-visual">
                <div class="reference-hero-halo"></div>
                @if($heroBanner)
                    <a href="{{ route('banners.click',$heroBanner) }}" data-banner-impression="{{ route('banners.impression',$heroBanner) }}">
                        <picture>
                            @if($heroBanner->mobile_image_path)<source media="(max-width:767px)" srcset="{{ asset('storage/'.$heroBanner->mobile_image_path) }}">@endif
                            <img src="{{ asset('storage/'.$heroBanner->image_path) }}" alt="{{ $heroBanner->alt ?: $heroBanner->name }}">
                        </picture>
                    </a>
                @else
                    <img src="https://images.unsplash.com/photo-1492144534655-ae79c964c9d7?auto=format&fit=crop&w=1100&q=82" alt="خودرو و قطعات اتوکار">
                @endif
                <span class="reference-part-chip chip-one"><i class="bi bi-disc"></i> ترمز</span>
                <span class="reference-part-chip chip-two"><i class="bi bi-fan"></i> موتور</span>
                <span class="reference-part-chip chip-three"><i class="bi bi-lightning-charge"></i> برق خودرو</span>
            </div>
        </div>

        <div class="reference-vehicle-finder" id="vehicle-picker" data-vehicle-picker>
            <div class="reference-finder-title"><i class="bi bi-car-front"></i><div><b>خودروی خود را انتخاب کنید</b><span>قطعات سازگار را دقیق‌تر پیدا کنید</span></div></div>
            <label><span>برند خودرو</span><select class="form-select" data-vehicle-make><option value="">انتخاب برند</option>@foreach($vehicleMakes as $make)<option value="{{ $make->id }}">{{ $make->name }}</option>@endforeach</select></label>
            <label><span>مدل</span><select class="form-select" data-vehicle-model disabled><option>انتخاب مدل</option></select></label>
            <label><span>سال / نسل</span><select class="form-select" data-vehicle-generation disabled><option>انتخاب سال</option></select></label>
            <label><span>تیپ</span><select class="form-select" data-vehicle-trim disabled><option>انتخاب تیپ</option></select></label>
            <button class="btn btn-danger" type="button" data-vehicle-submit>نمایش قطعات سازگار <i class="bi bi-chevron-left"></i></button>
        </div>
    </div>
</section>

<section class="container reference-category-strip" aria-label="دسته‌بندی قطعات">
    @foreach($categories->take(10) as $category)
        @php($icon = $category->icon ?: $categoryIcons[$loop->index % count($categoryIcons)])
        <a href="{{ route('category.show',$category) }}" class="reference-category-tile">
            <span class="reference-category-icon"><i class="bi {{ str_starts_with($icon,'bi-') ? $icon : 'bi-gear-wide-connected' }}"></i></span>
            <b>{{ $category->name }}</b>
        </a>
    @endforeach
    <a href="{{ route('search') }}" class="reference-category-tile"><span class="reference-category-icon"><i class="bi bi-grid"></i></span><b>سایر دسته‌بندی‌ها</b></a>
</section>

@if($specialProducts->isNotEmpty() || $products->isNotEmpty())
<section class="container reference-commerce-section">
    <div class="reference-service-panel">
        <div><i class="bi bi-shield-check"></i><span><b>ضمانت اصالت کالا</b><small>تضمین اصالت تمامی قطعات</small></span></div>
        <div><i class="bi bi-truck"></i><span><b>ارسال سریع</b><small>ارسال به سراسر کشور</small></span></div>
        <div><i class="bi bi-arrow-counterclockwise"></i><span><b>۷ روز ضمانت بازگشت</b><small>بدون قید و شرط</small></span></div>
        <div><i class="bi bi-headset"></i><span><b>پشتیبانی تخصصی</b><small>پاسخگوی شما هستیم</small></span></div>
    </div>

    @if($featuredDeal)
        @php($dealPrice = $featuredDeal->priceSnapshot())
        <aside class="reference-deal-card">
            <div class="reference-deal-title"><i class="bi bi-lightning-charge-fill"></i><b>فروش ویژه امروز</b></div>
            @if($dealPrice['ends_at'])
                <div class="reference-deal-countdown" data-countdown="{{ $dealPrice['ends_at']->toIso8601String() }}"><i class="bi bi-clock"></i><span>در حال محاسبه…</span></div>
            @endif
            <a class="reference-deal-media" href="{{ route('product.show',$featuredDeal) }}">
                @if($featuredDeal->media->first())<img src="{{ asset('storage/'.$featuredDeal->media->first()->path) }}" alt="{{ $featuredDeal->name }}">@else<i class="bi bi-gear-wide-connected"></i>@endif
            </a>
            <a class="reference-deal-name" href="{{ route('product.show',$featuredDeal) }}">{{ $featuredDeal->name }}</a>
            <div class="reference-deal-price">
                @if($dealPrice['compare_at_price'] > $dealPrice['final_price'])<del>{{ number_format($dealPrice['compare_at_price']) }}</del>@endif
                <strong>{{ number_format($dealPrice['final_price']) }} <small>ریال</small></strong>
            </div>
            <a class="btn btn-outline-danger w-100" href="{{ route('search') }}">مشاهده همه پیشنهادها <i class="bi bi-chevron-left"></i></a>
        </aside>
    @endif

    <div class="reference-products-area">
        <div class="reference-section-heading"><div><span>پیشنهادهای اتوکار</span><h2>قطعات منتخب</h2></div><a href="{{ route('search') }}">مشاهده همه</a></div>
        <div class="reference-product-grid">
            @foreach(($specialProducts->isNotEmpty() ? $specialProducts : $products)->take(4) as $product)
                @include('storefront.partials.product-card',['product'=>$product])
            @endforeach
        </div>
    </div>
</section>
@endif

@if($middleBanners->isNotEmpty())
<section class="container reference-banner-row">
    @foreach($middleBanners->take(2) as $banner)
        <a href="{{ route('banners.click',$banner) }}" data-banner-impression="{{ route('banners.impression',$banner) }}"><img loading="lazy" src="{{ asset('storage/'.$banner->image_path) }}" alt="{{ $banner->alt ?: $banner->name }}"></a>
    @endforeach
</section>
@endif

<section class="container reference-latest-section">
    <div class="reference-section-heading"><div><span>تازه‌ها</span><h2>آخرین قطعات فروشگاه</h2></div><a href="{{ route('search') }}">همه محصولات</a></div>
    <div class="reference-product-grid reference-product-grid-wide">
        @foreach($products->take(5) as $product)
            @include('storefront.partials.product-card',['product'=>$product])
        @endforeach
    </div>
</section>

<section class="container reference-brand-strip" id="brands">
    <button type="button" aria-label="برند قبلی"><i class="bi bi-chevron-right"></i></button>
    @foreach($brands->take(7) as $brand)
        <a href="{{ route('brand.show',$brand) }}">
            @if($brand->logo_path)<img loading="lazy" src="{{ asset('storage/'.$brand->logo_path) }}" alt="{{ $brand->name }}">@else<span class="reference-brand-mark">{{ mb_substr($brand->name,0,2) }}</span>@endif
            <b>{{ $brand->name }}</b>
        </a>
    @endforeach
    <button type="button" aria-label="برند بعدی"><i class="bi bi-chevron-left"></i></button>
</section>

@if($bottomBanners->isNotEmpty())
<section class="container reference-banner-row reference-banner-row-bottom">
    @foreach($bottomBanners->take(2) as $banner)
        <a href="{{ route('banners.click',$banner) }}" data-banner-impression="{{ route('banners.impression',$banner) }}"><img loading="lazy" src="{{ asset('storage/'.$banner->image_path) }}" alt="{{ $banner->alt ?: $banner->name }}"></a>
    @endforeach
</section>
@endif
@endsection
