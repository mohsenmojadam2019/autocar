<?php

namespace App\Services\Identity;

use App\Models\OtpCode;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;

class OtpService
{
    /** Creates a short-lived OTP after enforcing a per-mobile request throttle. */
    public function issue(string $mobile, string $purpose = 'login'): string
    {
        $key = 'otp:issue:'.$purpose.':'.$mobile;
        if (RateLimiter::tooManyAttempts($key, 3)) {
            throw new RuntimeException('درخواست کد بیش از حد مجاز است. چند دقیقه بعد دوباره تلاش کنید.');
        }

        RateLimiter::hit($key, 120);
        $code = (string) random_int(100000, 999999);

        OtpCode::query()->where('mobile', $mobile)->where('purpose', $purpose)->whereNull('consumed_at')->delete();
        OtpCode::query()->create([
            'mobile' => $mobile,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(2),
        ]);

        return $code;
    }

    /** Verifies a single-use OTP and consumes it atomically after a successful match. */
    public function verify(string $mobile, string $code, string $purpose = 'login'): bool
    {
        $otp = OtpCode::query()->where('mobile', $mobile)->where('purpose', $purpose)
            ->whereNull('consumed_at')->where('expires_at', '>', now())->latest('id')->first();

        if (! $otp) {
            return false;
        }

        $otp->increment('attempts');
        if ($otp->attempts > 5 || ! Hash::check($code, $otp->code_hash)) {
            return false;
        }

        $otp->update(['consumed_at' => now()]);
        return true;
    }
}
