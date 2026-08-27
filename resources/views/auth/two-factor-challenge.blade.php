@extends('layouts.storefront')
@section('title','تأیید دومرحله‌ای | اتوکار')
@section('content')<div class="auth-wrap"><div class="auth-card ac-surface"><h1>تأیید امنیتی</h1><p>کد ۶ رقمی برنامه Authenticator را وارد کنید.</p><form method="post" action="{{ route('2fa.verify') }}">@csrf<input class="form-control mb-3" name="code" inputmode="numeric" autocomplete="one-time-code" required><button class="btn btn-primary w-100">تأیید</button></form></div></div>@endsection
