<?php

namespace App\Domain\Cart\Services;

use App\Domain\Cart\Models\Cart;
use App\Domain\Sms\Services\SmsService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AbandonedCartService
{
    public function __construct(private readonly SmsService $sms) {}

    /** Materializes recoverable carts that have been inactive long enough and still contain items. */
    public function discover(int $inactiveMinutes = 60): int
    {
        $count = 0;
        Cart::query()->where('status', 'active')->whereNotNull('user_id')->where('last_activity_at', '<=', now()->subMinutes(max(15, $inactiveMinutes)))->whereHas('items')->with('user')->chunkById(100, function ($carts) use (&$count): void {
            foreach ($carts as $cart) {
                DB::table('cart_recoveries')->updateOrInsert(['cart_id' => $cart->id], [
                    'token' => DB::table('cart_recoveries')->where('cart_id', $cart->id)->value('token') ?: Str::random(48),
                    'status' => 'pending', 'eligible_at' => now(), 'expires_at' => now()->addDays(7), 'created_at' => now(), 'updated_at' => now(),
                ]);
                $count++;
            }
        });

        return $count;
    }

    /** Sends one recovery SMS per eligible cart, respecting marketing suppression and active cart state. */
    public function sendDue(int $limit = 100): int
    {
        $rows = DB::table('cart_recoveries')->join('carts', 'carts.id', '=', 'cart_recoveries.cart_id')->join('users', 'users.id', '=', 'carts.user_id')
            ->where('cart_recoveries.status', 'pending')->where('carts.status', 'active')->where('cart_recoveries.eligible_at', '<=', now())
            ->where(fn ($q) => $q->whereNull('cart_recoveries.expires_at')->orWhere('cart_recoveries.expires_at', '>', now()))
            ->whereNotNull('users.mobile')->select('cart_recoveries.*', 'users.mobile', 'users.id as user_id')->limit(min(max($limit, 1), 500))->get();
        $sent = 0;
        foreach ($rows as $row) {
            if (DB::table('marketing_suppressions')->where('channel', 'sms')->where('value', $row->mobile)->exists()) {
                DB::table('cart_recoveries')->where('id', $row->id)->update(['status' => 'suppressed', 'updated_at' => now()]);

                continue;
            }
            $url = route('cart.recover', ['token' => $row->token]);
            $this->sms->queue($row->mobile, 'سبد خرید شما در اتوکار ذخیره شده است: '.$url, $row->user_id, 'abandoned_cart');
            DB::table('cart_recoveries')->where('id', $row->id)->update(['status' => 'sent', 'sent_at' => now(), 'updated_at' => now()]);
            $sent++;
        }

        return $sent;
    }

    public function recover(string $token, int $userId): Cart
    {
        $recovery = DB::table('cart_recoveries')->where('token', $token)->firstOrFail();
        $cart = Cart::query()->whereKey($recovery->cart_id)->where('user_id', $userId)->where('status', 'active')->firstOrFail();
        DB::table('cart_recoveries')->where('id', $recovery->id)->update(['status' => 'recovered', 'recovered_at' => now(), 'updated_at' => now()]);

        return $cart;
    }
}
