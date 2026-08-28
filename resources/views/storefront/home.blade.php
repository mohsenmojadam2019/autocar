@extends('layouts.storefront')
@section('title','اتوکار | فروشگاه تخصصی قطعات و لوازم یدکی')
@section('meta_description','خرید قطعات خودرو با جست‌وجوی کد فنی، انتخاب خودرو، کنترل سازگاری و ضمانت اصالت کالا')
@section('content')
@php($featuredProducts = $specialProducts->isNotEmpty() ? $specialProducts->take(5) : $products->take(5))
<div class="container ac-home">
    <section class="ac-hero" aria-labelledby="home-hero-title">
        <div class="ac-hero-content">
            <span class="ac-hero-kicker">بیش از هزاران قطعه از برندهای معتبر</span>
            <h1 id="home-hero-title">قطعات اصلی با بهترین کیفیت <strong>برای خودروی شما</strong></h1>
            <p class="ac-hero-subtitle">خودروی خود را انتخاب کنید تا فقط قطعات سازگار، جایگزین‌های مناسب و مشخصات فنی مرتبط را ببینید.</p>

            <div class="ac-vehicle-box" id="vehicle-picker" data-vehicle-picker>
                <div class="ac-vehicle-box-title"><span>خودروی</span> <strong>خود</strong> <span>را انتخاب کنید</span></div>
                <div class="ac-vehicle-fields">
                    <div class="ac-vehicle-field"><label for="hero-make">برند خودرو</label><select id="hero-make" class="form-select" data-vehicle-make><option value="">انتخاب برند</option>@foreach($vehicleMakes as $make)<option value="{{ $make->id }}">{{ $make->name }}</option>@endforeach</select></div>
                    <div class="ac-vehicle-field"><label for="hero-model">مدل خودرو</label><select id="hero-model" class="form-select" data-vehicle-model disabled><option value="">انتخاب مدل</option></select></div>
                    <div class="ac-vehicle-field"><label for="hero-generation">سال / نسل</label><select id="hero-generation" class="form-select" data-vehicle-generation disabled><option value="">انتخاب سال</option></select></div>
                    <div class="ac-vehicle-field"><label for="hero-trim">تیپ / موتور</label><select id="hero-trim" class="form-select" data-vehicle-trim disabled><option value="">انتخاب تیپ</option></select></div>
                </div>
                <button class="btn btn-primary ac-vehicle-submit" type="button" data-vehicle-submit>جست‌وجوی قطعات مناسب <i class="bi bi-search"></i></button>
            </div>
        </div>
        <div class="ac-hero-art"><img src="{{ asset('demo/hero-autocar.svg') }}" alt="خودرو و مجموعه‌ای از قطعات یدکی" fetchpriority="high"></div>
    </section>

    <section class="ac-category-strip" aria-label="دسته‌بندی‌های قطعات">
        <a class="ac-category-tile ac-category-all" href="{{ route('search') }}"><i class="bi bi-grid ac-category-icon"></i><span>مشاهده همه دسته‌بندی‌ها</span></a>
        @foreach($categories->take(8) as $category)
            <a class="ac-category-tile" href="{{ route('category.show',$category) }}">
                <i class="{{ $category->icon ?: match($loop->index % 8){0=>'bi bi-gear-wide-connected',1=>'bi bi-disc',2=>'bi bi-car-front',3=>'bi bi-lightning-charge',4=>'bi bi-droplet',5=>'bi bi-wrench-adjustable',6=>'bi bi-fan',default=>'bi bi-tools'} }} ac-category-icon"></i>
                <span>{{ $category->name }}</span>
            </a>
        @endforeach
    </section>

    <section class="ac-commerce-row">
        <aside class="ac-trust-panel" aria-label="مزایای خرید از اتوکار">
            <div class="ac-trust-item"><span class="ac-trust-icon"><i class="bi bi-shield-check"></i></span><div><strong>ضمانت اصالت کالا</strong><span>کالای معتبر با اطلاعات شفاف برند و کد فنی</span></div></div>
            <div class="ac-trust-item"><span class="ac-trust-icon"><i class="bi bi-truck"></i></span><div><strong>ارسال سریع</strong><span>ارسال سفارش به سراسر کشور از انبار فعال</span></div></div>
            <div class="ac-trust-item"><span class="ac-trust-icon"><i class="bi bi-arrow-counterclockwise"></i></span><div><strong>۷ روز ضمانت بازگشت</strong><span>طبق شرایط بازگشت و وضعیت نصب قطعه</span></div></div>
            <div class="ac-trust-item"><span class="ac-trust-icon"><i class="bi bi-headset"></i></span><div><strong>پشتیبانی تخصصی</strong><span>مشاوره برای انتخاب قطعه سازگار با خودروی شما</span></div></div>
        </aside>

        <div class="ac-products-panel">
            <div class="ac-panel-heading"><h2>محصولات پیشنهادی</h2><a href="{{ route('search') }}">مشاهده همه <i class="bi bi-chevron-left"></i></a></div>
            <div class="ac-products-grid">
                @forelse($featuredProducts as $product)
                    @include('storefront.partials.product-card',['product'=>$product])
                @empty
                    <div class="empty-state"><i class="bi bi-box-seam"></i><p>محصولات پس از اجرای Seeder در این بخش نمایش داده می‌شوند.</p></div>
                @endforelse
            </div>
        </div>

        <aside class="ac-promo-panel">
            <div class="ac-promo-copy"><span class="ac-promo-label">تخفیف ویژه</span><h3>انواع لاستیک و قطعات مصرفی</h3><strong>تا <span>۲۰٪</span> تخفیف</strong><br><a class="btn btn-primary" href="{{ route('search',['q'=>'لاستیک']) }}">مشاهده و خرید</a></div>
            <img src="{{ asset('demo/products/tire.svg') }}" alt="پیشنهاد ویژه لاستیک خودرو" loading="lazy">
        </aside>
    </section>

    <section class="ac-brand-strip" id="brands" aria-label="برندهای قطعات">
        <div class="ac-brand-track">
            @forelse($brands->take(8) as $brand)
                <a class="ac-brand-item" href="{{ route('brand.show',$brand) }}">
                    @if($brand->logo_path)<img src="{{ str_starts_with($brand->logo_path,'demo/') ? asset($brand->logo_path) : asset('storage/'.$brand->logo_path) }}" alt="{{ $brand->name }}">@else<span>{{ $brand->name }}</span>@endif
                </a>
            @empty
                @foreach(['BOSCH','Valeo','MANN FILTER','Mobil 1','MAHLE','NGK','Gates','TOKICO'] as $demoBrand)<span class="ac-brand-item">{{ $demoBrand }}</span>@endforeach
            @endforelse
        </div>
    </section>

    @if($middleBanners->isNotEmpty())
        <section class="section-block"><div class="hero-banner-stack">@foreach($middleBanners as $banner)<a class="hero-banner" href="{{ route('banners.click',$banner) }}" data-banner-impression="{{ route('banners.impression',$banner) }}"><picture>@if($banner->mobile_image_path)<source media="(max-width:767px)" srcset="{{ asset('storage/'.$banner->mobile_image_path) }}">@endif<img loading="lazy" src="{{ asset('storage/'.$banner->image_path) }}" alt="{{ $banner->alt ?: $banner->name }}"></picture></a>@endforeach</div></section>
    @endif

    <section class="section-block pb-4">
        <div class="section-heading"><div><span class="eyebrow">تازه‌های فروشگاه</span><h2>قطعات جدید</h2></div><a href="{{ route('search') }}">همه محصولات <i class="bi bi-chevron-left"></i></a></div>
        <div class="product-grid">@foreach($products->take(8) as $product)@include('storefront.partials.product-card',['product'=>$product])@endforeach</div>
    </section>
</div>
@endsection
