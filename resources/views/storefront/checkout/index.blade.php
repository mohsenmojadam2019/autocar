@extends('layouts.storefront')
@section('title','تسویه‌حساب | اتوکار')
@section('content')
@php($gatewayLabels=['zarinpal'=>'زرین‌پال','idpay'=>'IDPay','zibal'=>'زیبال','nextpay'=>'NextPay','payir'=>'Pay.ir','behpardakht'=>'به‌پرداخت ملت','saman'=>'سامان','parsian'=>'پارسیان','pasargad'=>'پاسارگاد','card_to_card'=>'کارت‌به‌کارت','cash_on_delivery'=>'پرداخت در محل'])
<div class="container py-5">
    <nav class="breadcrumb-line"><a href="{{ route('home') }}">خانه</a><span>/</span><a href="{{ route('cart.index') }}">سبد خرید</a><span>/</span><span>تسویه‌حساب</span></nav>
    <div class="d-flex justify-content-between align-items-end gap-3 mb-4 flex-wrap"><div><span class="eyebrow">مرحله نهایی خرید</span><h1 class="h3 fw-bold mb-1">تسویه‌حساب امن</h1><p class="text-muted small mb-0">آدرس، نوع فاکتور، ارسال و پرداخت را بررسی کنید.</p></div><div class="small text-muted"><i class="bi bi-lock text-primary"></i> محاسبات قیمت و موجودی سمت سرور تأیید می‌شوند</div></div>

    <form method="post" action="{{ route('checkout.store') }}" class="checkout-layout" data-checkout-form>
        @csrf
        <div class="d-grid gap-3">
            <section class="ac-surface checkout-form">
                <div class="d-flex align-items-center gap-3 mb-4"><span class="ac-trust-icon"><i class="bi bi-geo-alt"></i></span><div><h4 class="mb-1">اطلاعات گیرنده</h4><small class="text-muted">اطلاعات ارسال سفارش</small></div></div>
                <div class="row g-3">
                    <div class="col-md-6"><label class="form-label">نام کامل</label><input class="form-control" name="full_name" value="{{ old('full_name',$defaultAddress?->full_name ?? auth()->user()->name) }}" required></div>
                    <div class="col-md-6"><label class="form-label">موبایل</label><input class="form-control" name="mobile" value="{{ old('mobile',$defaultAddress?->mobile ?? auth()->user()->mobile) }}" required></div>
                    <div class="col-md-6"><label class="form-label">استان</label><input class="form-control" name="province" value="{{ old('province',$defaultAddress?->province) }}" data-shipping-province required></div>
                    <div class="col-md-6"><label class="form-label">شهر</label><input class="form-control" name="city" value="{{ old('city',$defaultAddress?->city) }}" data-shipping-city required></div>
                    <div class="col-md-4"><label class="form-label">کدپستی</label><input class="form-control" name="postal_code" value="{{ old('postal_code',$defaultAddress?->postal_code) }}"></div>
                    <div class="col-md-8"><label class="form-label">نشانی کامل</label><textarea class="form-control" name="address" rows="3" required>{{ old('address',$defaultAddress?->address) }}</textarea></div>
                </div>
            </section>

            <section class="ac-surface checkout-form">
                <div class="d-flex align-items-center gap-3 mb-4"><span class="ac-trust-icon"><i class="bi bi-receipt"></i></span><div><h4 class="mb-1">نوع فاکتور</h4><small class="text-muted">خرید حقیقی یا حقوقی با Snapshot اطلاعات مالی</small></div></div>
                <div class="row g-3">
                    <div class="col-md-4"><label class="form-label">نوع خریدار فاکتور</label><select class="form-select" name="invoice_kind" data-invoice-kind><option value="natural" @selected(old('invoice_kind')==='natural')>فاکتور حقیقی</option><option value="legal" @selected(old('invoice_kind')==='legal')>فاکتور حقوقی</option></select></div>
                    <div class="col-md-8"><label class="form-label">پروفایل مالی</label><select class="form-select" name="billing_profile_id" data-billing-profile><option value="">پروفایل فاکتور را انتخاب کنید</option>@foreach($billingProfiles as $profile)<option value="{{ $profile->id }}" data-kind="{{ $profile->type }}" @selected(old('billing_profile_id')==$profile->id)>{{ $profile->title ?: ($profile->type==='legal'?$profile->company_name:$profile->full_name) }} — {{ $profile->type==='legal'?'حقوقی':'حقیقی' }}</option>@endforeach</select><small class="d-block mt-2"><a class="text-primary" href="{{ route('account.billing.index') }}" target="_blank"><i class="bi bi-pencil-square"></i> مدیریت پروفایل‌های فاکتور</a></small></div>
                </div>
            </section>

            <section class="ac-surface checkout-form">
                <div class="d-flex align-items-center gap-3 mb-4"><span class="ac-trust-icon"><i class="bi bi-truck"></i></span><div><h4 class="mb-1">ارسال و پرداخت</h4><small class="text-muted">روش تحویل و درگاه پرداخت</small></div></div>
                <h6 class="fw-bold">روش ارسال</h6>
                <div data-shipping-rates class="mb-4">@forelse($shippingRates as $rate)<label class="d-flex align-items-center justify-content-between gap-2 border rounded-3 p-3 mb-2 bg-white"><span class="d-flex align-items-center gap-2"><input type="radio" name="shipping_method_id" value="{{ $rate->id }}"><span>{{ $rate->name }}</span></span><b class="text-primary">{{ number_format($rate->price) }} ریال</b></label>@empty<div class="text-muted small border rounded-3 p-3 bg-light">استان و شهر را وارد کنید تا روش‌های ارسال محاسبه شوند.</div>@endforelse</div>

                <h6 class="fw-bold">روش پرداخت</h6>
                <div class="payment-options">@foreach($gateways as $key)<label><input type="radio" name="gateway" value="{{ $key }}" @checked($loop->first)><span class="d-block mt-1">{{ $gatewayLabels[$key] ?? $key }}</span></label>@endforeach</div>
                @if($wallet && $wallet->balance>0)<label class="form-check mt-3 border rounded-3 p-3 ps-5 bg-light"><input class="form-check-input" type="checkbox" name="use_wallet" value="1"><span><b>استفاده از کیف پول</b><small class="d-block text-muted">موجودی {{ number_format($wallet->balance) }} ریال</small></span></label>@endif
            </section>
        </div>

        <aside class="cart-summary ac-surface">
            <h4>خلاصه سفارش</h4>
            <div class="small text-muted mb-3">{{ number_format($cart->items->sum('quantity')) }} قلم کالا</div>
            @foreach($cart->items as $item)<div class="gap-2"><span class="text-truncate">{{ $item->product->name }} × {{ $item->quantity }}</span><b>{{ number_format($item->unit_price*$item->quantity) }}</b></div>@endforeach
            <hr>
            <label class="form-label">کد تخفیف</label><div class="input-group mb-3"><input class="form-control" name="coupon" value="{{ old('coupon') }}" placeholder="مثلاً AUTOCAR"><span class="input-group-text bg-white"><i class="bi bi-ticket-perforated text-primary"></i></span></div>
            <div class="total"><span>جمع فعلی کالا</span><b class="text-primary">{{ number_format($cart->subtotal()) }} ریال</b></div>
            <small class="text-muted d-block mb-3">ارسال، مالیات، تخفیف، قیمت زمان‌دار و کیف پول در سرور دوباره محاسبه می‌شوند.</small>
            <button class="btn btn-primary btn-lg w-100">ثبت سفارش و پرداخت <i class="bi bi-chevron-left"></i></button>
            <div class="text-center small text-muted mt-3"><i class="bi bi-shield-lock"></i> پرداخت امن و قابل پیگیری</div>
        </aside>
    </form>
</div>
@endsection
