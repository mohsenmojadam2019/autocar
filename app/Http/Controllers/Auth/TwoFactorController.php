<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Identity\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class TwoFactorController extends Controller
{
    /** Shows or initializes TOTP enrollment for the authenticated account. */
    public function setup(Request $request, TwoFactorService $twoFactor): View
    {
        $user = $request->user();
        if (! $user->two_factor_secret) {
            $user->forceFill(['two_factor_secret' => Crypt::encryptString($twoFactor->generateSecret())])->save();
        }
        $secret = Crypt::decryptString($user->two_factor_secret);

        return view('auth.two-factor-setup', [
            'secret' => $secret,
            'uri' => $twoFactor->provisioningUri($user, $secret),
            'confirmed' => (bool) $user->two_factor_confirmed_at,
        ]);
    }

    /** Confirms enrollment only after a valid authenticator code. */
    public function confirm(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();
        abort_unless($user->two_factor_secret, 422);
        $secret = Crypt::decryptString($user->two_factor_secret);
        if (! $twoFactor->verify($secret, $data['code'])) {
            return back()->withErrors(['code' => 'کد احراز هویت دومرحله‌ای معتبر نیست.']);
        }
        $user->forceFill(['two_factor_confirmed_at' => now()])->save();
        $request->session()->put('two_factor_verified_at', now()->timestamp);

        return back()->with('success', 'احراز هویت دومرحله‌ای فعال شد.');
    }

    /** Shows the login-time second-factor challenge. */
    public function challenge(): View
    {
        return view('auth.two-factor-challenge');
    }

    /** Marks the current session as second-factor verified. */
    public function verifyChallenge(Request $request, TwoFactorService $twoFactor): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);
        $user = $request->user();
        abort_unless($user->two_factor_secret && $user->two_factor_confirmed_at, 403);
        if (! $twoFactor->verify(Crypt::decryptString($user->two_factor_secret), $data['code'])) {
            return back()->withErrors(['code' => 'کد صحیح نیست یا منقضی شده است.']);
        }
        $request->session()->put('two_factor_verified_at', now()->timestamp);

        return redirect()->intended(route('admin.dashboard'));
    }

    /** Disables TOTP only after confirming the account password. */
    public function disable(Request $request): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        if (! Hash::check($data['password'], $request->user()->password)) {
            return back()->withErrors(['password' => 'رمز عبور صحیح نیست.']);
        }
        $request->user()->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
        $request->session()->forget('two_factor_verified_at');

        return back()->with('success', 'احراز هویت دومرحله‌ای غیرفعال شد.');
    }
}
