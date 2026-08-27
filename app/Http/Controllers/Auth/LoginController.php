<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Cart\Services\CartService;
use App\Domain\Sms\Services\SmsService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Identity\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function create(): View
    {
        return view('auth.login');
    }

    public function password(Request $request, AuditLogger $audit, CartService $carts): RedirectResponse
    {
        $data = $request->validate(['login' => ['required', 'string', 'max:190'], 'password' => ['required', 'string', 'max:200']]);
        $key = 'login:'.sha1($request->ip().'|'.$data['login']);
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return back()->withErrors(['login' => 'تعداد تلاش ورود بیش از حد مجاز است.'])->onlyInput('login');
        }
        $user = User::query()->where('email', $data['login'])->orWhere('mobile', $data['login'])->first();
        if (! $user || ! $user->is_active || ! Hash::check($data['password'], $user->password)) {
            RateLimiter::hit($key, 60);

            return back()->withErrors(['login' => 'اطلاعات ورود صحیح نیست.'])->onlyInput('login');
        }
        RateLimiter::clear($key);
        $cartToken = $request->session()->get('cart_token');
        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->forget('two_factor_verified_at');
        $cart = $carts->claimAfterLogin($user->id, $cartToken);
        $request->session()->put('cart_token', $cart->token);
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $audit->log('auth.login.password', $user);

        return redirect()->intended(route('home'));
    }

    public function requestOtp(Request $request, OtpService $otp, SmsService $sms): RedirectResponse
    {
        $data = $request->validate(['mobile' => ['required', 'regex:/^09\d{9}$/']]);
        $user = User::query()->where('mobile', $data['mobile'])->where('is_active', true)->first();
        if (! $user) {
            return back()->withErrors(['mobile' => 'کاربر فعالی با این شماره یافت نشد.']);
        }
        $code = $otp->issue($data['mobile']);
        $request->session()->put('otp_mobile', $data['mobile']);
        if (app()->isLocal()) {
            $request->session()->flash('development_otp', $code);
        } else {
            $sms->queue($data['mobile'], 'کد ورود اتوکار: '.$code, $user->id, 'otp_login');
        }

        return back()->with('otp_requested', true);
    }

    public function verifyOtp(Request $request, OtpService $otp, AuditLogger $audit, CartService $carts): RedirectResponse
    {
        $data = $request->validate(['mobile' => ['required', 'regex:/^09\d{9}$/'], 'code' => ['required', 'digits:6']]);
        if (! $otp->verify($data['mobile'], $data['code'])) {
            return back()->withErrors(['code' => 'کد ورود نامعتبر یا منقضی شده است.']);
        }
        $user = User::query()->where('mobile', $data['mobile'])->where('is_active', true)->firstOrFail();
        $cartToken = $request->session()->get('cart_token');
        Auth::login($user, true);
        $request->session()->regenerate();
        $request->session()->forget('two_factor_verified_at');
        $cart = $carts->claimAfterLogin($user->id, $cartToken);
        $request->session()->put('cart_token', $cart->token);
        $user->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();
        $audit->log('auth.login.otp', $user);

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
