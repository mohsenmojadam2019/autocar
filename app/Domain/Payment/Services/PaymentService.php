<?php

namespace App\Domain\Payment\Services;

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

    public function __construct(private readonly SettingsRepository $settings, private readonly OrderService $orders) {}

    /** Creates an idempotent online or manual transaction without exposing provider secrets. */
    public function initiate(Order $order, string $gatewayName, string $callbackUrl, ?string $idempotencyKey = null): array
    {
        $idempotencyKey ??= (string) Str::uuid();
        if ($existing = PaymentTransaction::query()->where('idempotency_key', $idempotencyKey)->first()) {
            return ['transaction' => $existing, 'redirect_url' => null, 'manual' => in_array($existing->gateway, self::MANUAL_GATEWAYS, true)];
        }

        if (in_array($gatewayName, self::MANUAL_GATEWAYS, true)) {
            $transaction = PaymentTransaction::query()->create([
                'order_id' => $order->id,
                'user_id' => $order->user_id,
                'gateway' => $gatewayName,
                'status' => $gatewayName === 'cash_on_delivery' ? 'cash_on_delivery' : 'awaiting_manual_proof',
                'idempotency_key' => $idempotencyKey,
                'authority' => 'manual-'.Str::uuid(),
                'amount' => $order->grand_total,
                'request_payload' => ['manual' => true],
            ]);

            return ['transaction' => $transaction, 'redirect_url' => null, 'manual' => true];
        }

        $gateway = $this->gateway($gatewayName);
        $request = $gateway->request($order, $callbackUrl);
        $transaction = PaymentTransaction::query()->create([
            'order_id' => $order->id,
            'user_id' => $order->user_id,
            'gateway' => $gateway->name(),
            'status' => $request->successful ? 'pending' : 'failed',
            'idempotency_key' => $idempotencyKey,
            'authority' => $request->authority,
            'amount' => $order->grand_total,
            'request_payload' => $request->payload,
            'failure_message' => $request->message,
        ]);

        return ['transaction' => $transaction, 'redirect_url' => $request->redirectUrl, 'manual' => false];
    }

    /** Verifies an online callback exactly once under a database row lock. */
    public function verify(PaymentTransaction $transaction, array $callback): PaymentTransaction
    {
        if (in_array($transaction->gateway, self::MANUAL_GATEWAYS, true)) {
            throw new RuntimeException('پرداخت دستی از Callback قابل تأیید نیست.');
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
            if ($result->successful && $locked->order && $locked->order->status === OrderStatus::PendingPayment) {
                $this->orders->transition($locked->order, OrderStatus::Paid, 'پرداخت تأیید شد: '.$locked->gateway);
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
