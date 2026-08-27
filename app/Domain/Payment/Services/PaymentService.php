<?php

namespace App\Domain\Payment\Services;

use App\Domain\Inventory\Services\InventoryReservationService;
use App\Domain\Order\Enums\OrderStatus;
use App\Domain\Order\Models\Order;
use App\Domain\Order\Services\OrderService;
use App\Domain\Payment\Contracts\PaymentGateway;
use App\Domain\Payment\Gateways\ConfigurableJsonGateway;
use App\Domain\Payment\Gateways\ZarinpalGateway;
use App\Domain\Payment\Models\PaymentTransaction;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class PaymentService
{
    /** Online provider names supported through dedicated/configurable adapters. */
    public const ONLINE_GATEWAYS = ['zarinpal', 'idpay', 'zibal', 'nextpay', 'payir', 'behpardakht', 'saman', 'parsian', 'pasargad'];

    /** Offline/manual methods that never trust a browser callback. */
    public const MANUAL_GATEWAYS = ['card_to_card', 'cash_on_delivery'];

    public function __construct(
        private readonly SettingsRepository $settings,
        private readonly OrderService $orders,
        private readonly InventoryReservationService $reservations,
    ) {}

    /** Creates an idempotent transaction for the amount remaining after wallet usage and settles wallet-only orders immediately. */
    public function initiate(Order $order, string $gatewayName, string $callbackUrl, ?string $idempotencyKey = null): array
    {
        $idempotencyKey ??= (string) Str::uuid();
        if ($existing = PaymentTransaction::query()->where('idempotency_key', $idempotencyKey)->first()) {
            return [
                'transaction' => $existing,
                'redirect_url' => null,
                'manual' => in_array($existing->gateway, self::MANUAL_GATEWAYS, true),
                'settled' => $existing->status === 'verified',
            ];
        }

        $amountDue = max(0, (int) $order->grand_total - (int) $order->wallet_total);
        if ($amountDue === 0) {
            $transaction = DB::transaction(function () use ($order, $idempotencyKey): PaymentTransaction {
                $transaction = PaymentTransaction::query()->create([
                    'order_id' => $order->id,
                    'user_id' => $order->user_id,
                    'gateway' => 'wallet',
                    'status' => 'verified',
                    'idempotency_key' => $idempotencyKey,
                    'authority' => 'wallet-'.Str::uuid(),
                    'reference_id' => 'wallet-'.$order->number,
                    'amount' => 0,
                    'request_payload' => ['wallet_total' => $order->wallet_total],
                    'verify_payload' => ['settled_by_wallet' => true],
                    'verified_at' => now(),
                ]);
                if ($order->status === OrderStatus::PendingPayment) {
                    $this->orders->transition($order, OrderStatus::Paid, 'سفارش به‌طور کامل از کیف پول تسویه شد.');
                }
                $this->reservations->commitOrder($order);

                return $transaction;
            });

            return ['transaction' => $transaction, 'redirect_url' => null, 'manual' => false, 'settled' => true];
        }

        if (in_array($gatewayName, self::MANUAL_GATEWAYS, true)) {
            $transaction = PaymentTransaction::query()->create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'gateway' => $gatewayName,
                'status' => $gatewayName === 'cash_on_delivery' ? 'cash_on_delivery' : 'awaiting_manual_proof',
                'idempotency_key' => $idempotencyKey,
                'authority' => 'manual-'.Str::uuid(),
                'amount' => $amountDue,
                'request_payload' => ['manual' => true, 'wallet_total' => $order->wallet_total],
            ]);

            if ($gatewayName === 'cash_on_delivery') {
                $this->reservations->commitOrder($order);
            }

            return ['transaction' => $transaction, 'redirect_url' => null, 'manual' => true, 'settled' => false];
        }

        $gateway = $this->gateway($gatewayName);
        $request = $gateway->request($order, $callbackUrl, $amountDue);
        $transaction = PaymentTransaction::query()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'gateway' => $gateway->name(),
            'status' => $request->successful ? 'pending' : 'failed',
            'idempotency_key' => $idempotencyKey,
            'authority' => $request->authority,
            'amount' => $amountDue,
            'request_payload' => array_merge($request->payload, ['wallet_total' => $order->wallet_total]),
            'failure_message' => $request->message,
        ]);

        return ['transaction' => $transaction, 'redirect_url' => $request->redirectUrl, 'manual' => false, 'settled' => false];
    }

    /** Verifies an online callback exactly once and commits previously reserved inventory after successful payment. */
    public function verify(PaymentTransaction $transaction, array $callback): PaymentTransaction
    {
        if (in_array($transaction->gateway, self::MANUAL_GATEWAYS, true) || $transaction->gateway === 'wallet') {
            throw new RuntimeException('این روش پرداخت از Callback قابل تأیید نیست.');
        }
        if ($transaction->status === 'verified') {
            return $transaction;
        }

        return DB::transaction(function () use ($transaction, $callback): PaymentTransaction {
            $locked = PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($locked->status === 'verified') {
                return $locked;
            }
            $result = $this->gateway($locked->gateway)->verify($locked, $callback);
            $locked->update([
                'callback_payload' => $callback,
                'verify_payload' => $result->payload,
                'status' => $result->successful ? 'verified' : 'failed',
                'reference_id' => $result->referenceId,
                'failure_message' => $result->message,
                'verified_at' => $result->successful ? now() : null,
            ]);
            if ($result->successful && $locked->order) {
                if ($locked->order->status === OrderStatus::PendingPayment) {
                    $this->orders->transition($locked->order, OrderStatus::Paid, 'پرداخت تأیید شد: '.$locked->gateway);
                }
                $this->reservations->commitOrder($locked->order);
            }

            return $locked->fresh();
        });
    }

    /** Returns every checkout payment key supported by this installation. */
    public function supportedGateways(): array
    {
        return array_merge(self::ONLINE_GATEWAYS, self::MANUAL_GATEWAYS);
    }

    /** Resolves online provider adapters; direct banks remain configurable without checkout coupling. */
    public function gateway(string $name): PaymentGateway
    {
        if ($name === 'zarinpal') {
            return app(ZarinpalGateway::class);
        }
        if (in_array($name, array_diff(self::ONLINE_GATEWAYS, ['zarinpal']), true)) {
            return new ConfigurableJsonGateway($this->settings, $name);
        }

        throw new RuntimeException('درگاه پرداخت پشتیبانی نمی‌شود.');
    }
}
