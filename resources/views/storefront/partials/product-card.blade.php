@php($cardPrice = $product->priceSnapshot())
<article class="product-card">
    <a class="product-media" href="{{ route('product.show', $product) }}">
        @if($product->media->first())
            <img loading="lazy" src="{{ asset('storage/'.$product->media->first()->path) }}" alt="{{ $product->media->first()->alt ?: $product->name }}">
        @else
            <div class="product-placeholder"><i class="bi bi-gear-wide-connected"></i></div>
        @endif
        <span class="auth-badge">{{ $product->authenticity?->value ?? 'company' }}</span>
        @if($cardPrice['discount_amount'] > 0)
            <span class="discount-badge">{{ $cardPrice['badge_text'] }}</span>
        @endif
    </a>
    <div class="product-body">
        <div class="d-flex justify-content-between align-items-center gap-2">
            <small>{{ $product->brand?->name ?: 'AutoCar' }}</small>
            <div class="product-card-actions">
                @auth
                    <form method="post" action="{{ route('wishlist.store', $product) }}">@csrf<button type="submit" title="افزودن به علاقه‌مندی" aria-label="افزودن به علاقه‌مندی"><i class="bi bi-heart"></i></button></form>
                @endauth
                <form method="post" action="{{ route('compare.store', $product) }}">@csrf<button type="submit" title="مقایسه" aria-label="افزودن به مقایسه"><i class="bi bi-arrow-left-right"></i></button></form>
            </div>
        </div>
        <h3><a href="{{ route('product.show', $product) }}">{{ $product->name }}</a></h3>
        <div class="product-code">SKU: {{ $product->sku }} @if($product->oem_code) · OEM: {{ $product->oem_code }}@endif</div>
        @if($cardPrice['ends_at'])
            <div class="sale-countdown small" data-countdown="{{ $cardPrice['ends_at']->toIso8601String() }}"><i class="bi bi-clock"></i> <span>در حال محاسبه…</span></div>
        @endif
        <div class="product-bottom">
            <div class="product-card-price">
                @if($cardPrice['compare_at_price'] > $cardPrice['final_price'])
                    <del>{{ number_format($cardPrice['compare_at_price']) }}</del>
                @endif
                <strong>{{ number_format($cardPrice['final_price']) }} <small>ریال</small></strong>
            </div>
            <form method="post" action="{{ route('cart.add', $product) }}">@csrf<button class="round-add" aria-label="افزودن به سبد"><i class="bi bi-plus-lg"></i></button></form>
        </div>
    </div>
</article>
