<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Sms\Services\SmsService;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Identity\OtpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class PasswordRecoveryController extends Controller
{
    /** Shows mobile OTP password-recovery form. */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /** Issues a reset OTP and queues SMS without disclosing whether arbitrary numbers exist. */
    public function requestOtp(Request $request, OtpService $otp, SmsService $sms): RedirectResponse
    {
        $data = $request->validate(['mobile' => ['required', 'regex:/^09\d{9}$/']]);
        $user = User::query()->where('mobile', $data['mobile'])->where('is_active', true)->first();
        if ($user) {
            $code = $otp->issue($data['mobile'], 'password_reset');
            if (app()->isLocal()) {
                $request->session()->flash('development_otp', $code);
            } else {
                $sms->queue($data['mobile'], 'کد بازیابی رمز اتوکار: '.$code, $user->id, 'password_reset');
            }
        }
        $request->session()->put('password_reset_mobile', $data['mobile']);

        return back()->with('otp_requested', true)->with('status', 'اگر شماره در سامانه فعال باشد، کد بازیابی ارسال می‌شود.');
    }

    /** Verifies the OTP and replaces the password with a framework-hashed value. */
    public function reset(Request $request, OtpService $otp): RedirectResponse
    {
        $data = $request->validate(['mobile' => ['required', 'regex:/^09\d{9}$/'], 'code' => ['required', 'digits:6'], 'password' => ['required', 'string', 'min:8', 'confirmed']]);
        if (! $otp->verify($data['mobile'], $data['code'], 'password_reset')) {
            return back()->withErrors(['code' => 'کد بازیابی نامعتبر یا منقضی است.']);
        }
        $user = User::query()->where('mobile', $data['mobile'])->where('is_active', true)->firstOrFail();
        $user->update(['password' => Hash::make($data['password'])]);
        $request->session()->forget('password_reset_mobile');

        return redirect()->route('login')->with('success', 'رمز عبور تغییر کرد.');
    }
}
