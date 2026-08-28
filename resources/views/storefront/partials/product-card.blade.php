@php($cardPrice = $product->priceSnapshot())
<article class="product-card reference-product-card">
    <div class="reference-product-top-actions">
        @auth
            <form method="post" action="{{ route('wishlist.store', $product) }}">@csrf<button type="submit" title="افزودن به علاقه‌مندی" aria-label="افزودن به علاقه‌مندی"><i class="bi bi-heart"></i></button></form>
        @endauth
        <form method="post" action="{{ route('compare.store', $product) }}">@csrf<button type="submit" title="مقایسه" aria-label="افزودن به مقایسه"><i class="bi bi-arrow-left-right"></i></button></form>
    </div>

    <a class="product-media reference-product-media" href="{{ route('product.show', $product) }}">
        @if($product->media->first())
            <img loading="lazy" src="{{ asset('storage/'.$product->media->first()->path) }}" alt="{{ $product->media->first()->alt ?: $product->name }}">
        @else
            <div class="product-placeholder"><i class="bi bi-gear-wide-connected"></i></div>
        @endif
        @if($cardPrice['discount_amount'] > 0)
            <span class="discount-badge">{{ $cardPrice['badge_text'] }}</span>
        @endif
    </a>

    <div class="product-body reference-product-body">
        <small class="reference-product-brand">{{ $product->brand?->name ?: 'AutoCar' }}</small>
        <h3><a href="{{ route('product.show', $product) }}">{{ $product->name }}</a></h3>
        <div class="reference-product-rating" aria-label="امتیاز محصول"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star"></i><span>SKU {{ $product->sku }}</span></div>

        @if($cardPrice['ends_at'])
            <div class="sale-countdown small" data-countdown="{{ $cardPrice['ends_at']->toIso8601String() }}"><i class="bi bi-clock"></i><span>در حال محاسبه…</span></div>
        @endif

        <div class="reference-product-price">
            @if($cardPrice['compare_at_price'] > $cardPrice['final_price'])
                <del>{{ number_format($cardPrice['compare_at_price']) }}</del>
            @endif
            <strong>{{ number_format($cardPrice['final_price']) }} <small>ریال</small></strong>
        </div>

        <form class="reference-add-form" method="post" action="{{ route('cart.add', $product) }}">
            @csrf
            <button type="submit" class="btn btn-outline-danger"><i class="bi bi-cart3"></i> افزودن به سبد</button>
        </form>
    </div>
</article>
