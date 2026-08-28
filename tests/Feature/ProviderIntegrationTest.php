<?php

use App\Domain\Order\Models\Order;
use App\Domain\Payment\Gateways\ZarinpalGateway;
use App\Domain\Sms\Providers\KavenegarProvider;
use App\Models\User;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\Http;

it('maps Zarinpal sandbox request responses into the payment contract', function (): void {
    $user = User::factory()->create();
    $order = Order::query()->create([
        'number' => 'AC-ZP-1', 'user_id' => $user->id, 'status' => 'pending_payment', 'source' => 'web',
        'subtotal' => 250000, 'discount_total' => 0, 'shipping_total' => 0, 'tax_total' => 0, 'grand_total' => 250000,
        'shipping_address' => [], 'billing_address' => [],
    ]);
    $settings = app(SettingsRepository::class);
    $settings->set('payments.zarinpal.sandbox', true, 'payments', 'bool');
    $settings->set('payments.zarinpal.merchant_id', 'sandbox-merchant', 'payments', 'string', true);
    Http::fake(['https://sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response(['data' => ['authority' => 'A0000000000000000000000000000000001']])]);

    $result = app(ZarinpalGateway::class)->request($order, 'https://autocar.test/payment/callback');

    expect($result->successful)->toBeTrue()
        ->and($result->authority)->toBe('A0000000000000000000000000000000001')
        ->and($result->redirectUrl)->toContain('/pg/StartPay/');
    Http::assertSent(fn ($request) => $request['amount'] === 250000 && $request['merchant_id'] === 'sandbox-merchant');
});

it('fails Zarinpal request safely when provider returns an error', function (): void {
    $settings = app(SettingsRepository::class);
    $settings->set('payments.zarinpal.sandbox', true, 'payments', 'bool');
    $settings->set('payments.zarinpal.merchant_id', 'sandbox-merchant', 'payments', 'string', true);
    $order = Order::query()->create([
        'number' => 'AC-ZP-ERR', 'status' => 'pending_payment', 'source' => 'web', 'subtotal' => 1000,
        'discount_total' => 0, 'shipping_total' => 0, 'tax_total' => 0, 'grand_total' => 1000,
        'shipping_address' => [], 'billing_address' => [],
    ]);
    Http::fake(['https://sandbox.zarinpal.com/pg/v4/payment/request.json' => Http::response(['errors' => ['message' => 'invalid merchant']], 422)]);

    $result = app(ZarinpalGateway::class)->request($order, 'https://autocar.test/callback');
    expect($result->successful)->toBeFalse()->and($result->message)->toBe('invalid merchant');
});

it('sends and checks delivery through the Kavenegar provider contract', function (): void {
    $settings = app(SettingsRepository::class);
    $settings->set('sms.kavenegar.api_key', 'test-api-key', 'sms', 'string', true);
    $settings->set('sms.kavenegar.sender', '100000', 'sms');
    Http::fake([
        'https://api.kavenegar.com/v1/test-api-key/sms/send.json' => Http::response(['entries' => [['messageid' => 12345]]]),
        'https://api.kavenegar.com/v1/test-api-key/sms/status.json*' => Http::response(['entries' => [['status' => 10]]]),
    ]);

    $provider = app(KavenegarProvider::class);
    $sent = $provider->send('09120000000', 'پیام تست اتوکار');
    $delivery = $provider->deliveryStatus($sent['id']);

    expect($sent['id'])->toBe('12345')
        ->and($delivery['successful'])->toBeTrue()
        ->and($delivery['status'])->toBe(10);
});
