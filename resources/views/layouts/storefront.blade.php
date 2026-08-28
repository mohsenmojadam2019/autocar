@php($siteMenu=app(\App\Domain\Content\Services\MegaMenuService::class)->tree('main','desktop'))
@php($viteReady=file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','اتوکار | فروشگاه تخصصی قطعات خودرو')</title>
    <meta name="description" content="@yield('meta_description','فروش تخصصی قطعات و لوازم یدکی خودرو با جست‌وجوی کد فنی و سازگاری دقیق با خودرو')">
    @include('storefront.partials.structured-data')
    @if($viteReady)
        @vite(['resources/css/app.css','resources/css/extensions.css','resources/css/ux.css','resources/css/autocar-theme.css','resources/js/vendor.js','resources/js/app.js','resources/js/ux.js'])
    @else
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.rtl.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="{{ route('assets.css') }}">
        <link rel="stylesheet" href="{{ route('assets.extensions.css') }}">
        <link rel="stylesheet" href="{{ route('assets.ux.css') }}">
        <link rel="stylesheet" href="{{ route('assets.autocar-theme.css') }}">
    @endif
    @stack('head')
</head>
<body class="storefront-body">
<a class="skip-link" href="#main-content">رفتن به محتوای اصلی</a>

<div class="ac-utility">
    <div class="container">
        <div class="ac-utility-items">
            <span class="ac-utility-item"><i class="bi bi-shield-check"></i> ضمانت اصالت کالا</span>
            <span class="ac-utility-item"><i class="bi bi-truck"></i> ارسال سریع به سراسر کشور</span>
            <span class="ac-utility-item"><i class="bi bi-arrow-counterclockwise"></i> ۷ روز ضمانت بازگشت کالا</span>
        </div>
        <div class="ac-utility-help"><i class="bi bi-headset"></i> پشتیبانی تخصصی ۷ روز هفته</div>
    </div>
</div>

<header class="site-header">
    <div class="ac-header-main">
        <div class="container">
            <a class="ac-brand ac-header-logo" href="{{ route('home') }}" aria-label="AutoCar">
                <span class="ac-brand-mark" aria-hidden="true"></span>
                <span class="ac-brand-copy"><span>AUTO<span>CAR</span></span><small>قطعات یدکی خودرو</small></span>
            </a>

            <form class="header-search" action="{{ route('search') }}" role="search">
                <input name="q" value="{{ request('q') }}" autocomplete="off" data-search-input aria-label="جست‌وجوی قطعات" placeholder="نام قطعه، برند، کد فنی یا خودروی خود را جست‌وجو کنید...">
                <button type="submit" aria-label="جست‌وجو"><i class="bi bi-search"></i></button>
                <div class="search-suggestions" data-search-suggestions hidden></div>
            </form>

            <div class="ac-header-actions">
                <a class="ac-header-action ac-cart-action" href="{{ route('cart.index') }}"><i class="bi bi-cart3"></i><span>سبد خرید</span><b class="ac-cart-count">{{ session('cart_count', 0) }}</b></a>
                <a class="ac-header-action" href="{{ route('wishlist.index') }}"><i class="bi bi-heart"></i><span>علاقه‌مندی‌ها</span></a>
                @auth
                    <a class="ac-header-action" href="{{ route('account.dashboard') }}"><i class="bi bi-person"></i><span>حساب من</span></a>
                @else
                    <a class="ac-header-action" href="{{ route('login') }}"><i class="bi bi-person"></i><span>ورود / ثبت‌نام</span></a>
                @endauth
            </div>
        </div>
    </div>

    <nav class="ac-main-nav" aria-label="منوی اصلی">
        <div class="container">
            <div class="ac-nav-links">
                <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">صفحه اصلی</a>
                <button type="button" data-mega-trigger aria-expanded="false" aria-controls="catalog-mega-menu">قطعات خودرو <i class="bi bi-chevron-down me-1"></i></button>
                <a href="{{ route('home') }}#brands">برندها</a>
                <a href="{{ route('home') }}#vehicle-picker">خودروی من</a>
                <a href="{{ route('blog.index') }}">بلاگ و آموزش</a>
                <a href="{{ route('part-request.create') }}">درخواست قطعه</a>
                <a href="{{ route('tracking') }}">پیگیری سفارش</a>
            </div>
            <button class="ac-catalog-trigger" type="button" data-mega-trigger aria-expanded="false" aria-controls="catalog-mega-menu"><i class="bi bi-list"></i> دسته‌بندی محصولات</button>
        </div>
    </nav>

    <div class="mega-menu" id="catalog-mega-menu" data-mega-menu hidden>
        <div class="container mega-dynamic">
            @forelse($siteMenu as $item)
                <div class="mega-column"><ul class="mega-tree list-unstyled mb-0">@include('storefront.partials.menu-node', ['node' => $item])</ul></div>
            @empty
                <div class="mega-empty"><i class="bi bi-grid fs-2 text-primary"></i><b>دسته‌بندی‌ها از پنل مدیریت قابل تنظیم هستند</b></div>
            @endforelse
        </div>
    </div>
</header>

@if(session('success'))<div class="container mt-3"><div class="alert alert-success border-0 shadow-sm">{{ session('success') }}</div></div>@endif
@if(session('status'))<div class="container mt-3"><div class="alert alert-info border-0 shadow-sm">{{ session('status') }}</div></div>@endif
@if($errors->any())<div class="container mt-3"><div class="alert alert-danger border-0 shadow-sm"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

<main id="main-content" tabindex="-1">@yield('content')</main>

<footer class="site-footer">
    <div class="ac-footer-trust">
        <div class="container ac-footer-trust-grid">
            <div class="ac-footer-trust-item"><i class="bi bi-patch-check"></i><div><strong>ضمانت اصالت کالا</strong><small>کالای معتبر و قابل رهگیری</small></div></div>
            <div class="ac-footer-trust-item"><i class="bi bi-truck"></i><div><strong>ارسال سریع</strong><small>ارسال به سراسر کشور</small></div></div>
            <div class="ac-footer-trust-item"><i class="bi bi-arrow-counterclockwise"></i><div><strong>۷ روز بازگشت</strong><small>طبق شرایط بازگشت کالا</small></div></div>
            <div class="ac-footer-trust-item"><i class="bi bi-headset"></i><div><strong>مشاوره تخصصی</strong><small>کمک برای انتخاب قطعه سازگار</small></div></div>
        </div>
    </div>
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-4">
                <a class="ac-brand mb-3" href="{{ route('home') }}"><span class="ac-brand-mark"></span><span class="ac-brand-copy"><span>AUTO<span>CAR</span></span><small>قطعات یدکی خودرو</small></span></a>
                <p class="mt-3 mb-0">فروشگاه تخصصی قطعات خودرو با جست‌وجوی کد فنی، کنترل سازگاری خودرو و پشتیبانی خرید حقیقی، حقوقی و عمده.</p>
            </div>
            <div class="col-6 col-lg-2"><h6>خرید</h6><a href="{{ route('search') }}">همه قطعات</a><a href="{{ route('cart.index') }}">سبد خرید</a><a href="{{ route('compare.index') }}">مقایسه</a><a href="{{ route('wishlist.index') }}">علاقه‌مندی‌ها</a></div>
            <div class="col-6 col-lg-2"><h6>خدمات</h6><a href="{{ route('tracking') }}">پیگیری سفارش</a><a href="{{ route('part-request.create') }}">درخواست قطعه</a><a href="{{ route('faq') }}">سؤالات متداول</a></div>
            <div class="col-lg-4"><h6>خرید مطمئن قطعات خودرو</h6><p class="small mb-0">قبل از خرید خودروی خود را انتخاب کنید تا وضعیت سازگاری قطعه، مشخصات فنی و گزینه‌های جایگزین دقیق‌تر نمایش داده شوند.</p></div>
        </div>
    </div>
</footer>

@if(!$viteReady)
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" defer></script>
<script src="{{ route('assets.js') }}" defer></script>
<script src="{{ route('assets.ux.js') }}" defer></script>
@endif
</body>
</html>
