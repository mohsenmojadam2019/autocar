<?php

namespace App\Domain\Payment\Gateways;

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Contracts\PaymentGateway;
use App\Domain\Payment\DTOs\PaymentRequest;
use App\Domain\Payment\DTOs\PaymentVerification;
use App\Domain\Payment\Models\PaymentTransaction;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Http;

class ZarinpalGateway implements PaymentGateway
{
    public function __construct(private readonly SettingsRepository $settings) {}
    /** Returns the provider key used in transaction rows and admin settings. */ public function name(): string { return 'zarinpal'; }

    /** Requests an authority from Zarinpal Sandbox or Production based on encrypted settings. */
    public function request(Order $order, string $callbackUrl): PaymentRequest
    {
        $sandbox = (bool) $this->settings->get('payments.zarinpal.sandbox', true);
        $base = $sandbox ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
        $payload = ['merchant_id'=>$this->settings->get('payments.zarinpal.merchant_id'),'amount'=>$order->grand_total,'callback_url'=>$callbackUrl,'description'=>'AutoCar order '.$order->number,'metadata'=>['mobile'=>$order->user?->mobile ?? null]];
        $response = Http::timeout(15)->acceptJson()->post($base.'/pg/v4/payment/request.json', $payload);
        $data = $response->json() ?: [];
        $authority = data_get($data, 'data.authority');
        if (! $response->successful() || ! $authority) return new PaymentRequest(false, payload:$data, message:data_get($data,'errors.message','خطا در ایجاد پرداخت زرین‌پال'));
        return new PaymentRequest(true, $authority, $base.'/pg/StartPay/'.$authority, $data);
    }

    /** Verifies amount+authority server-to-server and never trusts callback status alone. */
    public function verify(PaymentTransaction $transaction, array $callback): PaymentVerification
    {
        if (($callback['Status'] ?? null) !== 'OK') return new PaymentVerification(false, payload:$callback, message:'پرداخت توسط کاربر لغو یا ناموفق شد.');
        $sandbox = (bool) $this->settings->get('payments.zarinpal.sandbox', true);
        $base = $sandbox ? 'https://sandbox.zarinpal.com' : 'https://payment.zarinpal.com';
        $payload = ['merchant_id'=>$this->settings->get('payments.zarinpal.merchant_id'),'amount'=>$transaction->amount,'authority'=>$transaction->authority];
        $response = Http::timeout(15)->acceptJson()->post($base.'/pg/v4/payment/verify.json', $payload);
        $data = $response->json() ?: [];
        $code = (int) data_get($data,'data.code',0);
        $ref = data_get($data,'data.ref_id');
        if (! $response->successful() || ! in_array($code,[100,101],true)) return new PaymentVerification(false,payload:$data,message:data_get($data,'errors.message','تأیید پرداخت ناموفق بود.'));
        return new PaymentVerification(true,(string)$ref,$data);
    }

    /** Zarinpal refund availability depends on merchant service; unsupported calls fail explicitly. */
    public function refund(PaymentTransaction $transaction, int $amount): PaymentVerification
    {
        return new PaymentVerification(false, message:'Refund API برای این حساب زرین‌پال پیکربندی نشده است.');
    }
}
