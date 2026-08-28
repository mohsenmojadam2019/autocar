@php($siteMenu=app(\App\Domain\Content\Services\MegaMenuService::class)->tree('main','desktop'))
@php($viteReady=file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','اتوکار | فروشگاه تخصصی قطعات خودرو')</title>
    <meta name="description" content="@yield('meta_description','فروش تخصصی قطعات و لوازم یدکی خودرو با جست‌وجوی کد فنی و سازگاری خودرو')">
    @include('storefront.partials.structured-data')
    @if($viteReady)
        @vite(['resources/css/app.css','resources/css/extensions.css','resources/css/ux.css','resources/js/vendor.js','resources/js/app.js','resources/js/ux.js'])
    @else
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.rtl.min.css">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
        <link rel="stylesheet" href="{{ route('assets.css') }}">
        <link rel="stylesheet" href="{{ route('assets.extensions.css') }}">
        <link rel="stylesheet" href="{{ route('assets.ux.css') }}">
    @endif
    @stack('head')
</head>
<body class="storefront-body reference-storefront">
<a class="skip-link" href="#main-content">رفتن به محتوای اصلی</a>

<div class="reference-trustbar">
    <div class="container reference-trustbar-grid">
        <span><i class="bi bi-shield-check"></i> ضمانت اصالت کالا</span>
        <span><i class="bi bi-truck"></i> ارسال سریع به سراسر کشور</span>
        <span><i class="bi bi-arrow-counterclockwise"></i> ۷ روز ضمانت بازگشت کالا</span>
        <span><i class="bi bi-headset"></i> پشتیبانی تخصصی ۷ روز هفته</span>
    </div>
</div>

<header class="site-header reference-header sticky-top">
    <div class="container reference-header-main">
        <a class="reference-logo" href="{{ route('home') }}" aria-label="اتوکار">
            <span class="reference-logo-auto">Auto</span><span class="reference-logo-car">Car</span><i class="bi bi-gear-wide-connected"></i>
        </a>

        <form class="header-search reference-search" action="{{ route('search') }}" role="search">
            <input name="q" value="{{ request('q') }}" autocomplete="off" data-search-input aria-label="جست‌وجوی قطعات" placeholder="نام قطعه، کد فنی یا خودرو را جستجو کنید">
            <button type="submit" aria-label="جستجو"><i class="bi bi-search"></i></button>
            <div class="search-suggestions" data-search-suggestions hidden></div>
        </form>

        <div class="reference-header-actions">
            @auth
                <a href="{{ route('account.dashboard') }}"><i class="bi bi-person"></i><span>حساب من</span></a>
            @else
                <a class="reference-login" href="{{ route('login') }}"><i class="bi bi-person"></i><span>ورود / ثبت‌نام</span></a>
            @endauth
            <a href="{{ route('wishlist.index') }}"><i class="bi bi-heart"></i><span>علاقه‌مندی‌ها</span></a>
            <a href="{{ route('cart.index') }}"><i class="bi bi-cart3"></i><span>سبد خرید</span></a>
        </div>
    </div>

    <nav class="reference-nav" aria-label="منوی اصلی">
        <div class="container reference-nav-inner">
            <a class="{{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">صفحه اصلی</a>
            <button class="reference-parts-trigger" type="button" data-mega-trigger aria-expanded="false" aria-controls="catalog-mega-menu">قطعات خودرو <i class="bi bi-chevron-down"></i></button>
            <a href="{{ route('search', ['q' => 'لوازم مصرفی']) }}">لوازم مصرفی</a>
            <a href="{{ route('search', ['q' => 'روغن فیلتر']) }}">روغن و فیلتر</a>
            <a href="{{ route('search', ['q' => 'باتری']) }}">باتری</a>
            <a href="{{ route('search', ['q' => 'لاستیک رینگ']) }}">لاستیک و رینگ</a>
            <a href="{{ route('search', ['q' => 'ابزار تجهیزات']) }}">ابزار و تجهیزات</a>
            <a href="{{ route('blog.index') }}">بلاگ</a>
            <a href="#footer-contact">تماس با ما</a>
        </div>
    </nav>

    <div class="mega-menu reference-mega" id="catalog-mega-menu" data-mega-menu hidden>
        <div class="container mega-dynamic">
            @forelse($siteMenu as $item)
                <div class="mega-column"><ul class="mega-tree list-unstyled mb-0">@include('storefront.partials.menu-node', ['node' => $item])</ul></div>
            @empty
                <div class="mega-empty"><b>دسته‌بندی‌های قطعات از پنل مدیریت قابل تنظیم هستند.</b></div>
            @endforelse
        </div>
    </div>
</header>

@if(session('success'))<div class="container mt-3"><div class="alert alert-success">{{ session('success') }}</div></div>@endif
@if(session('status'))<div class="container mt-3"><div class="alert alert-info">{{ session('status') }}</div></div>@endif
@if($errors->any())<div class="container mt-3"><div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif

<main id="main-content" tabindex="-1">@yield('content')</main>

<footer class="site-footer reference-footer mt-5" id="footer-contact">
    <div class="container py-5">
        <div class="row g-4">
            <div class="col-lg-4"><div class="reference-logo reference-logo-footer mb-3"><span class="reference-logo-auto">Auto</span><span class="reference-logo-car">Car</span></div><p>فروشگاه تخصصی قطعات خودرو با تمرکز بر اصالت، کد فنی و سازگاری دقیق با خودرو.</p></div>
            <div class="col-6 col-lg-2"><h6>خرید</h6><a href="{{ route('search') }}">قطعات</a><a href="{{ route('cart.index') }}">سبد خرید</a><a href="{{ route('compare.index') }}">مقایسه</a></div>
            <div class="col-6 col-lg-2"><h6>خدمات</h6><a href="{{ route('tracking') }}">پیگیری سفارش</a><a href="{{ route('part-request.create') }}">درخواست قطعه</a><a href="{{ route('faq') }}">سؤالات متداول</a></div>
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
