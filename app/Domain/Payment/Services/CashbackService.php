<?php

namespace App\Domain\Payment\Services;

use App\Domain\Order\Models\Order;
use App\Services\Settings\SettingsRepository;

class CashbackService
{
    public function __construct(private readonly WalletService $wallets, private readonly SettingsRepository $settings) {}

    /** Credits configured cashback exactly once through the immutable wallet ledger. */
    public function grant(Order $order): int
    {
        if (! $order->user_id) {
            return 0;
        }
        $percent = min(max((float) $this->settings->get('wallet.cashback_percent', 0), 0), 100);
        $max = max(0, (int) $this->settings->get('wallet.cashback_max', 0));
        $amount = (int) round(((int) $order->subtotal - (int) $order->discount_total) * ($percent / 100));
        if ($max > 0) {
            $amount = min($amount, $max);
        }

        return $amount > 0 ? $this->wallets->credit($order->user_id, $amount, 'cashback_order', $order->id, 'کش‌بک سفارش '.$order->number) : 0;
    }
}
