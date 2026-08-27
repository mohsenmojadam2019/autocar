@extends('layouts.storefront')

@section('title', ($product->meta_title ?: $product->name).' | اتوکار')
@section('meta_description', $product->meta_description ?: $product->summary)

@section('content')
<div class="container py-4">
    <nav class="breadcrumb-line" aria-label="breadcrumb">
        <a href="{{ route('home') }}">خانه</a><span>/</span>
        @foreach($product->categories->take(1) as $category)<a href="{{ route('category.show', $category) }}">{{ $category->name }}</a><span>/</span>@endforeach
        <span>{{ $product->name }}</span>
    </nav>

    <div class="product-detail">
        <section class="gallery ac-surface">
            <div class="main-image">
                @if($product->media->first())
                    <img data-main-image src="{{ asset('storage/'.$product->media->first()->path) }}" alt="{{ $product->media->first()->alt ?: $product->name }}">
                @else
                    <div class="product-placeholder large"><i class="bi bi-gear-wide-connected"></i></div>
                @endif
            </div>
            <div class="thumbs">
                @foreach($product->media as $media)
                    <button type="button" data-gallery-thumb data-src="{{ asset('storage/'.$media->path) }}" aria-label="نمایش تصویر {{ $loop->iteration }}"><img loading="lazy" src="{{ asset('storage/'.$media->path) }}" alt="{{ $media->alt ?: $product->name }}"></button>
                @endforeach
            </div>
        </section>

        <section class="product-info">
            <div class="product-brand">@if($product->brand)<a href="{{ route('brand.show', $product->brand) }}">{{ $product->brand->name }}</a>@endif</div>
            <h1>{{ $product->name }}</h1>
            <div class="d-flex align-items-center gap-3 flex-wrap mb-2">
                <div class="rating-summary" aria-label="امتیاز {{ number_format($ratingAverage, 1) }} از پنج"><i class="bi bi-star-fill"></i> {{ number_format($ratingAverage, 1) }} <small>({{ number_format($reviewsCount) }} نظر)</small></div>
                <div class="code-pills"><span>SKU {{ $product->sku }}</span>@if($product->oem_code)<span>OEM {{ $product->oem_code }}</span>@endif @if($product->manufacturer_code)<span>MPN {{ $product->manufacturer_code }}</span>@endif</div>
            </div>
            <p class="lead">{{ $product->summary }}</p>

            <div class="fitment-box">
                <i class="bi bi-car-front"></i>
                <div><b>بررسی سازگاری با خودرو</b><span>خودروی فعال گاراژ برای سازگاری دقیق قطعه بررسی می‌شود.</span></div>
                <a href="{{ auth()->check() ? route('account.garage') : route('login') }}">انتخاب خودرو</a>
            </div>

            <div class="buy-box ac-surface">
                @if($price['discount_amount'] > 0)
                    <div class="promotion-strip"><span>{{ $price['badge_text'] }}</span><b>{{ number_format($price['discount_amount']) }} ریال صرفه‌جویی</b></div>
                @endif
                <div class="price">
                    <small>قیمت فروش</small>
                    <strong>{{ number_format($price['final_price']) }} ریال</strong>
                    @if($price['compare_at_price'] > $price['final_price'])<del>{{ number_format($price['compare_at_price']) }}</del>@endif
                </div>
                @if($price['ends_at'])
                    <div class="sale-countdown mb-3" data-countdown="{{ $price['ends_at']->toIso8601String() }}"><i class="bi bi-clock-history"></i> پایان پیشنهاد: <span>در حال محاسبه…</span></div>
                @endif

                <form method="post" action="{{ route('cart.add', $product) }}">
                    @csrf
                    @if($product->variants->isNotEmpty())
                        <label class="form-label" for="variant_id">انتخاب مدل / تنوع</label>
                        <select class="form-select mb-2" id="variant_id" name="variant_id">
                            @foreach($product->variants as $variant)
                                <option value="{{ $variant->id }}">{{ $variant->name ?: $variant->sku }} — {{ number_format($variant->sale_price) }} ریال</option>
                            @endforeach
                        </select>
                    @endif
                    <div class="d-flex gap-2">
                        <input class="form-control qty-input" name="quantity" type="number" min="{{ max(1, $product->minimum_order_quantity) }}" @if($product->maximum_order_quantity) max="{{ $product->maximum_order_quantity }}" @endif value="{{ max(1, $product->minimum_order_quantity) }}" aria-label="تعداد">
                        <button class="btn btn-primary btn-lg flex-grow-1"><i class="bi bi-bag-plus"></i> افزودن به سبد</button>
                    </div>
                </form>

                <div class="d-flex gap-2 mt-2">
                    @auth<form class="flex-grow-1" method="post" action="{{ route('wishlist.store', $product) }}">@csrf<button class="btn btn-outline-secondary w-100"><i class="bi bi-heart"></i> علاقه‌مندی</button></form>@endauth
                    <form class="flex-grow-1" method="post" action="{{ route('compare.store', $product) }}">@csrf<button class="btn btn-outline-secondary w-100"><i class="bi bi-arrow-left-right"></i> مقایسه</button></form>
                </div>

                <ul class="buy-trust">
                    <li><i class="bi bi-patch-check"></i> اصالت: {{ $product->authenticity?->value }}</li>
                    <li><i class="bi bi-arrow-counterclockwise"></i> {{ $product->return_days }} روز مهلت بازگشت</li>
                    @if($product->warranty)<li><i class="bi bi-shield-check"></i> {{ $product->warranty }}</li>@endif
                </ul>
            </div>
        </section>
    </div>

    <section class="detail-tabs ac-surface mt-4">
        <h2>مشخصات و توضیحات</h2>
        <div class="row g-4">
            <div class="col-lg-7 product-description">{!! nl2br(e($product->description)) !!}</div>
            <div class="col-lg-5"><div class="spec-table">@forelse($product->attributeValues as $value)<div><span>{{ $value->attribute?->name }}</span><b>{{ $value->option?->value ?? $value->value }} {{ $value->attribute?->unit }}</b></div>@empty<div class="text-muted">مشخصاتی ثبت نشده است.</div>@endforelse</div></div>
        </div>
    </section>

    @if($complementary->isNotEmpty())
        <section class="section-block"><div class="section-heading"><div><span class="eyebrow">با هم بهترند</span><h2>محصولات مکمل</h2></div></div><div class="product-grid">@foreach($complementary as $item)@include('storefront.partials.product-card', ['product' => $item])@endforeach</div></section>
    @endif
    @if($alternatives->isNotEmpty())
        <section class="section-block"><div class="section-heading"><div><span class="eyebrow">انتخاب جایگزین</span><h2>محصولات جایگزین</h2></div></div><div class="product-grid">@foreach($alternatives as $item)@include('storefront.partials.product-card', ['product' => $item])@endforeach</div></section>
    @endif
    @if($upsells->isNotEmpty())
        <section class="section-block"><div class="section-heading"><div><span class="eyebrow">گزینه بالاتر</span><h2>پیشنهاد ارتقا</h2></div></div><div class="product-grid">@foreach($upsells as $item)@include('storefront.partials.product-card', ['product' => $item])@endforeach</div></section>
    @endif
    @if($similar->isNotEmpty())
        <section class="section-block"><div class="section-heading"><div><span class="eyebrow">پیشنهاد مشابه</span><h2>محصولات مشابه</h2></div></div><div class="product-grid">@foreach($similar as $item)@include('storefront.partials.product-card', ['product' => $item])@endforeach</div></section>
    @endif

    <section class="section-block reviews-grid" id="reviews">
        <div class="ac-surface p-4">
            <div class="section-heading"><div><span class="eyebrow">تجربه خریداران</span><h2>نظرات</h2></div><b>{{ number_format($ratingAverage, 1) }} / ۵</b></div>
            @forelse($reviews as $review)
                <article class="review-item"><div class="review-stars">@for($i=1;$i<=5;$i)<i class="bi {{ $i <= $review->rating ? 'bi-star-fill' : 'bi-star' }}"></i>@endfor @if($review->verified_purchase)<span class="status-badge">خرید تأییدشده</span>@endif</div><b>{{ $review->title }}</b><p>{{ $review->body }}</p>@if($review->admin_reply)<div class="admin-reply"><b>پاسخ اتوکار:</b> {{ $review->admin_reply }}</div>@endif</article>
            @empty
                <div class="empty-state py-4">هنوز نظری برای این محصول ثبت نشده است.</div>
            @endforelse
            @auth
                <form class="mt-4" method="post" action="{{ route('product.review', $product) }}">@csrf<div class="row g-2"><div class="col-md-3"><select class="form-select" name="rating" required>@for($i=5;$i>=1;$i--)<option value="{{ $i }}">{{ $i }} ستاره</option>@endfor</select></div><div class="col-md-9"><input class="form-control" name="title" maxlength="120" placeholder="عنوان نظر"></div><div class="col-12"><textarea class="form-control" name="body" rows="3" maxlength="2000" placeholder="نظر شما"></textarea></div></div><button class="btn btn-primary mt-2">ثبت نظر برای بررسی</button></form>
            @endauth
        </div>

        <div class="ac-surface p-4">
            <div class="section-heading"><div><span class="eyebrow">پرسش فنی</span><h2>پرسش و پاسخ</h2></div></div>
            @forelse($questions as $question)
                <article class="question-item"><b><i class="bi bi-question-circle"></i> {{ $question->question }}</b>@if($question->answer)<p><i class="bi bi-chat-left-text"></i> {{ $question->answer }}</p>@endif</article>
            @empty
                <div class="empty-state py-4">هنوز پرسشی ثبت نشده است.</div>
            @endforelse
            <form class="mt-4" method="post" action="{{ route('product.question', $product) }}">@csrf<textarea class="form-control" name="question" rows="3" maxlength="1500" required placeholder="سؤال درباره سازگاری، مشخصات یا نصب این قطعه"></textarea><button class="btn btn-outline-primary mt-2">ثبت پرسش</button></form>
        </div>
    </section>
</div>
@endsection
