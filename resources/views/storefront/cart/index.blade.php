@extends('layouts.storefront')
@section('title','سبد خرید | اتوکار')
@section('content')
<div class="container py-5">
    <nav class="breadcrumb-line"><a href="{{ route('home') }}">خانه</a><span>/</span><span>سبد خرید</span></nav>
    <div class="section-heading"><div><span class="eyebrow">مرحله اول خرید</span><h1>سبد خرید</h1></div><a href="{{ route('search') }}"><i class="bi bi-arrow-right"></i> ادامه خرید</a></div>

    @if($cart->items->isEmpty())
        <div class="empty-state ac-surface py-5"><i class="bi bi-cart3"></i><h3>سبد خرید شما خالی است</h3><p class="text-muted">قطعه موردنظر را با نام، کد OEM یا انتخاب خودرو پیدا کنید.</p><a class="btn btn-primary" href="{{ route('search') }}">مشاهده قطعات</a></div>
    @else
        <div class="cart-layout">
            <div>
                <div class="cart-lines ac-surface">
                    @foreach($cart->items as $item)
                        @php($media=$item->product->media->first())
                        @php($mediaUrl=$media ? (str_starts_with($media->path,'http') ? $media->path : (str_starts_with($media->path,'demo/') ? asset($media->path) : asset('storage/'.$media->path))) : null)
                        <div class="cart-line">
                            <a class="cart-thumb" href="{{ route('product.show',$item->product) }}">@if($mediaUrl)<img src="{{ $mediaUrl }}" alt="{{ $item->product->name }}">@else<i class="bi bi-gear"></i>@endif</a>
                            <div class="flex-grow-1"><small class="text-primary">{{ $item->product->brand?->name }}</small><a class="cart-title" href="{{ route('product.show',$item->product) }}">{{ $item->product->name }}</a><small>{{ $item->variant?->sku ?? $item->product->sku }}</small><strong>{{ number_format($item->unit_price) }} ریال</strong></div>
                            <form method="post" action="{{ route('cart.update',$item->id) }}">@csrf @method('patch')<label class="visually-hidden" for="qty-{{ $item->id }}">تعداد</label><input id="qty-{{ $item->id }}" class="form-control qty-input" type="number" name="quantity" min="1" value="{{ $item->quantity }}" onchange="this.form.submit()"></form>
                            <form method="post" action="{{ route('cart.remove',$item->id) }}">@csrf @method('delete')<button class="btn btn-light text-danger" aria-label="حذف {{ $item->product->name }}"><i class="bi bi-trash"></i></button></form>
                        </div>
                    @endforeach
                </div>
                <div class="ac-surface p-3 mt-3 d-flex gap-3 align-items-center"><span class="ac-trust-icon"><i class="bi bi-shield-check"></i></span><div><b>خرید مطمئن از اتوکار</b><div class="small text-muted">قیمت و موجودی قبل از ثبت سفارش در سرور دوباره کنترل می‌شوند.</div></div></div>
            </div>

            <aside class="cart-summary ac-surface">
                <h4>خلاصه سفارش</h4>
                <div><span>تعداد کالا</span><b>{{ number_format($cart->items->sum('quantity')) }}</b></div>
                <div><span>جمع کالاها</span><b>{{ number_format($cart->subtotal()) }} ریال</b></div>
                <div><span>هزینه ارسال</span><span class="text-muted">در مرحله بعد</span></div>
                <hr>
                <div class="total"><span>مبلغ فعلی</span><b class="text-primary">{{ number_format($cart->subtotal()) }} ریال</b></div>
                @auth<a class="btn btn-primary btn-lg w-100" href="{{ route('checkout.index') }}">ادامه فرایند خرید <i class="bi bi-chevron-left"></i></a>@else<a class="btn btn-primary btn-lg w-100" href="{{ route('login') }}">ورود و ادامه خرید</a>@endauth
                <div class="small text-muted mt-3 text-center"><i class="bi bi-lock"></i> اطلاعات پرداخت از مسیر امن ارسال می‌شوند</div>
            </aside>
        </div>
    @endif
</div>
@endsection
