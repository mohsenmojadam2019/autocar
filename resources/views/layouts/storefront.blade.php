<!doctype html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title','اتوکار | فروشگاه تخصصی قطعات خودرو')</title>
    <meta name="description" content="@yield('meta_description','فروش تخصصی قطعات و لوازم یدکی خودرو با جست‌وجوی کد فنی و سازگاری خودرو')">
    @vite(['resources/css/app.css','resources/js/app.js'])
    @stack('head')
</head>
<body class="storefront-body">
<div class="trustbar"><div class="container d-flex justify-content-between flex-wrap gap-2"><span><i class="bi bi-shield-check"></i> تضمین اصالت و سلامت کالا</span><span>مشاوره تخصصی قطعات خودرو</span><span>ارسال به سراسر ایران</span></div></div>
<header class="site-header sticky-top">
    <div class="container py-3"><div class="d-flex align-items-center gap-3">
        <a class="ac-brand flex-shrink-0" href="{{ route('home') }}">AUTO<span>CAR</span></a>
        <form class="header-search flex-grow-1" action="{{ route('search') }}"><i class="bi bi-search"></i><input name="q" value="{{ request('q') }}" autocomplete="off" data-search-input placeholder="نام قطعه، SKU، کد OEM یا برند را جست‌وجو کنید"><div class="search-suggestions" data-search-suggestions hidden></div></form>
        <div class="header-actions d-flex gap-2">
            @auth<a class="icon-btn" href="{{ route('account.dashboard') }}" aria-label="حساب"><i class="bi bi-person"></i></a>@else<a class="icon-btn" href="{{ route('login') }}" aria-label="ورود"><i class="bi bi-person"></i></a>@endauth
            <a class="icon-btn position-relative" href="{{ route('cart.index') }}" aria-label="سبد"><i class="bi bi-bag"></i></a>
        </div>
    </div></div>
    <nav class="main-nav border-top"><div class="container d-flex align-items-center gap-4 py-2">
        <button class="btn nav-catalog-btn" type="button" data-mega-trigger><i class="bi bi-grid"></i> همه قطعات</button>
        <a href="{{ route('search') }}">فروشگاه</a><a href="#brands">برندها</a><a href="#vehicle-picker">انتخاب خودرو</a><a href="/page/about">درباره ما</a><a href="/page/contact">تماس</a>
    </div></nav>
    <div class="mega-menu" data-mega-menu hidden><div class="container mega-inner"><div><h6>دسته‌بندی قطعات</h6><p>از موتور تا بدنه، برق، ترمز و مصرفی‌ها</p></div><div class="mega-links"><a href="{{ route('search') }}">همه محصولات</a><a href="{{ route('search',['q'=>'لنت']) }}">سیستم ترمز</a><a href="{{ route('search',['q'=>'فیلتر']) }}">فیلترها</a><a href="{{ route('search',['q'=>'شمع']) }}">برق و احتراق</a><a href="{{ route('search',['q'=>'جلوبندی']) }}">تعلیق و جلوبندی</a></div></div></div>
</header>
@if(session('success'))<div class="container mt-3"><div class="alert alert-success">{{ session('success') }}</div></div>@endif
@if($errors->any())<div class="container mt-3"><div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div></div>@endif
<main>@yield('content')</main>
<footer class="site-footer mt-5"><div class="container py-5"><div class="row g-4"><div class="col-lg-4"><div class="ac-brand mb-3">AUTO<span>CAR</span></div><p>فروشگاه تخصصی قطعات خودرو با تمرکز بر اصالت، کد فنی و سازگاری دقیق با خودرو.</p></div><div class="col-6 col-lg-2"><h6>خرید</h6><a href="{{ route('search') }}">قطعات</a><a href="{{ route('cart.index') }}">سبد خرید</a></div><div class="col-6 col-lg-2"><h6>خدمات</h6><a href="#vehicle-picker">گاراژ خودرو</a><a href="/page/returns">مرجوعی</a></div><div class="col-lg-4"><h6>پشتیبانی</h6><p>برای انتخاب قطعه، کد OEM یا مدل دقیق خودرو را آماده داشته باشید.</p></div></div></div></footer>
</body></html>
