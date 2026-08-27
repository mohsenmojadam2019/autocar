<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Payment\Services\PaymentService;
use App\Domain\Sms\Services\SmsService;
use App\Http\Controllers\Controller;
use App\Services\Operations\ProviderHealthService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProviderSettingsController extends Controller
{
    /** Shows provider configuration state without ever returning persisted secret values. */
    public function index(SettingsRepository $settings): View
    {
        $payments = collect(PaymentService::ONLINE_GATEWAYS)->map(fn ($name) => ['name' => $name, 'configured' => $name === 'zarinpal' ? (bool) $settings->get('payments.zarinpal.merchant_id') : (bool) $settings->get('payments.'.$name.'.api_key')]);
        $sms = collect(array_merge(['kavenegar'], SmsService::CONFIGURABLE_PROVIDERS))->map(fn ($name) => ['name' => $name, 'configured' => (bool) $settings->get('sms.'.$name.'.api_key')]);

        return view('admin.providers.index', compact('payments', 'sms'));
    }

    /** Stores encrypted gateway credentials and non-secret endpoint mapping. */
    public function payment(Request $request, SettingsRepository $settings): RedirectResponse
    {
        $data = $request->validate([
            'provider' => ['required', Rule::in(PaymentService::ONLINE_GATEWAYS)],
            'api_key' => ['nullable', 'string', 'max:2000'], 'merchant_id' => ['nullable', 'string', 'max:500'], 'sandbox' => ['nullable', 'boolean'],
            'request_url' => ['nullable', 'url', 'max:1000'], 'verify_url' => ['nullable', 'url', 'max:1000'], 'refund_url' => ['nullable', 'url', 'max:1000'], 'redirect_url' => ['nullable', 'string', 'max:1000'], 'health_url' => ['nullable', 'url', 'max:1000'],
            'authority_path' => ['nullable', 'string', 'max:100'], 'success_path' => ['nullable', 'string', 'max:100'], 'reference_path' => ['nullable', 'string', 'max:100'],
        ]);
        $base = 'payments.'.$data['provider'].'.';
        foreach (['request_url', 'verify_url', 'refund_url', 'redirect_url', 'health_url', 'authority_path', 'success_path', 'reference_path'] as $field) {
            if (array_key_exists($field, $data)) {
                $settings->set($base.$field, $data[$field] ?? '', 'payments');
            }
        }
        if (! empty($data['api_key'])) {
            $settings->set($base.'api_key', $data['api_key'], 'payments', 'string', true);
        }
        if (! empty($data['merchant_id'])) {
            $settings->set($base.'merchant_id', $data['merchant_id'], 'payments', 'string', true);
        }
        if ($data['provider'] === 'zarinpal') {
            $settings->set($base.'sandbox', $data['sandbox'] ?? false, 'payments', 'bool');
        }

        return back()->with('success', 'تنظیمات درگاه ذخیره شد؛ Secret قبلی هرگز در صفحه نمایش داده نمی‌شود.');
    }

    /** Stores encrypted SMS credentials and configurable adapter mapping. */
    public function sms(Request $request, SettingsRepository $settings): RedirectResponse
    {
        $providers = array_merge(['kavenegar'], SmsService::CONFIGURABLE_PROVIDERS);
        $data = $request->validate([
            'provider' => ['required', Rule::in($providers)], 'api_key' => ['nullable', 'string', 'max:2000'], 'sender' => ['nullable', 'string', 'max:100'],
            'send_url' => ['nullable', 'url', 'max:1000'], 'pattern_url' => ['nullable', 'url', 'max:1000'], 'status_url' => ['nullable', 'string', 'max:1000'], 'health_url' => ['nullable', 'url', 'max:1000'],
            'api_key_header' => ['nullable', 'string', 'max:100'], 'api_key_prefix' => ['nullable', 'string', 'max:100'], 'mobile_field' => ['nullable', 'string', 'max:100'], 'message_field' => ['nullable', 'string', 'max:100'], 'message_id_path' => ['nullable', 'string', 'max:100'],
        ]);
        $base = 'sms.'.$data['provider'].'.';
        foreach (['sender', 'send_url', 'pattern_url', 'status_url', 'health_url', 'api_key_header', 'api_key_prefix', 'mobile_field', 'message_field', 'message_id_path'] as $field) {
            if (array_key_exists($field, $data)) {
                $settings->set($base.$field, $data[$field] ?? '', 'sms');
            }
        }
        if (! empty($data['api_key'])) {
            $settings->set($base.'api_key', $data['api_key'], 'sms', 'string', true);
        }

        return back()->with('success', 'تنظیمات پیامک ذخیره شد.');
    }

    /** Runs safe non-billable configuration/health checks for all providers. */
    public function health(ProviderHealthService $health): RedirectResponse
    {
        $health->checkAll();

        return back()->with('success', 'Health Check Providerها ثبت شد.');
    }
}
