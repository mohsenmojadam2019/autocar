@php($cardPrice = $product->priceSnapshot())
@php($primaryMedia = $product->media->first())
@php($mediaUrl = $primaryMedia ? (str_starts_with($primaryMedia->path,'http://') || str_starts_with($primaryMedia->path,'https://') ? $primaryMedia->path : (str_starts_with($primaryMedia->path,'demo/') ? asset($primaryMedia->path) : asset('storage/'.$primaryMedia->path))) : null)
<article class="product-card">
    <a class="product-media" href="{{ route('product.show', $product) }}">
        @if($mediaUrl)
            <img loading="lazy" src="{{ $mediaUrl }}" alt="{{ $primaryMedia->alt ?: $product->name }}">
        @else
            <div class="product-placeholder"><i class="bi bi-gear-wide-connected"></i></div>
        @endif
        @if($cardPrice['discount_amount'] > 0)<span class="discount-badge">{{ $cardPrice['badge_text'] ?: round($cardPrice['discount_percent']).'٪' }}</span>@endif
    </a>
    <div class="product-body">
        <div class="d-flex justify-content-between align-items-center gap-2 position-relative">
            <small>{{ $product->brand?->name ?: 'AutoCar' }}</small>
            <div class="product-card-actions">
                @auth<form method="post" action="{{ route('wishlist.store', $product) }}">@csrf<button type="submit" title="افزودن به علاقه‌مندی" aria-label="افزودن به علاقه‌مندی"><i class="bi bi-heart"></i></button></form>@endauth
                <form method="post" action="{{ route('compare.store', $product) }}">@csrf<button type="submit" title="مقایسه" aria-label="افزودن به مقایسه"><i class="bi bi-arrow-left-right"></i></button></form>
            </div>
        </div>
        <h3><a href="{{ route('product.show', $product) }}">{{ $product->name }}</a></h3>
        <div class="product-code">{{ $product->oem_code ? 'OEM '.$product->oem_code : 'SKU '.$product->sku }}</div>
        <div class="ac-rating" aria-label="امتیاز نمونه"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><small>({{ ($product->id * 13) % 97 + 24 }})</small></div>
        @if($cardPrice['ends_at'])<div class="sale-countdown" data-countdown="{{ $cardPrice['ends_at']->toIso8601String() }}"><i class="bi bi-clock"></i><span>در حال محاسبه…</span></div>@endif
        <div class="product-bottom">
            <div class="product-card-price">
                @if($cardPrice['compare_at_price'] > $cardPrice['final_price'])<del>{{ number_format($cardPrice['compare_at_price']) }} ریال</del>@endif
                <strong>{{ number_format($cardPrice['final_price']) }} <small>ریال</small></strong>
            </div>
            <form method="post" action="{{ route('cart.add', $product) }}">@csrf<button class="round-add" aria-label="افزودن {{ $product->name }} به سبد"><i class="bi bi-cart3"></i> افزودن به سبد</button></form>
        </div>
    </div>
</article>
