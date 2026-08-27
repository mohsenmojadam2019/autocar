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
    public function __construct(private readonly SettingsRepository $settings, private readonly OrderService $orders) {}

    /** Creates an idempotent payment request and persists provider response before redirecting. */
    public function initiate(Order $order, string $gatewayName, string $callbackUrl, ?string $idempotencyKey = null): array
    {
        $idempotencyKey ??= (string) Str::uuid();
        if ($existing = PaymentTransaction::query()->where('idempotency_key',$idempotencyKey)->first()) return ['transaction'=>$existing,'redirect_url'=>null];
        $gateway=$this->gateway($gatewayName); $request=$gateway->request($order,$callbackUrl);
        $tx=PaymentTransaction::query()->create(['order_id'=>$order->id,'user_id'=>$order->user_id,'gateway'=>$gateway->name(),'status'=>$request->successful?'pending':'failed','idempotency_key'=>$idempotencyKey,'authority'=>$request->authority,'amount'=>$order->grand_total,'request_payload'=>$request->payload,'failure_message'=>$request->message]);
        return ['transaction'=>$tx,'redirect_url'=>$request->redirectUrl];
    }

    /** Verifies callback exactly once and transitions the associated order to Paid on trusted success. */
    public function verify(PaymentTransaction $transaction, array $callback): PaymentTransaction
    {
        if ($transaction->status === 'verified') return $transaction;
        return DB::transaction(function () use ($transaction,$callback): PaymentTransaction {
            $locked=PaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if ($locked->status === 'verified') return $locked;
            $result=$this->gateway($locked->gateway)->verify($locked,$callback);
            $locked->update(['callback_payload'=>$callback,'verify_payload'=>$result->payload,'status'=>$result->successful?'verified':'failed','reference_id'=>$result->referenceId,'failure_message'=>$result->message,'verified_at'=>$result->successful?now():null]);
            if ($result->successful && $locked->order && $locked->order->status === OrderStatus::PendingPayment) $this->orders->transition($locked->order,OrderStatus::Paid,'پرداخت تأیید شد: '.$locked->gateway);
            return $locked->fresh();
        });
    }

    /** Resolves the configured gateway without leaking provider-specific logic into controllers. */
    public function gateway(string $name): PaymentGateway
    {
        return match ($name) { 'zarinpal'=>app(ZarinpalGateway::class), 'idpay','zibal','nextpay','payir'=>new ConfigurableJsonGateway($this->settings,$name), default=>throw new RuntimeException('درگاه پرداخت پشتیبانی نمی‌شود.') };
    }
}
