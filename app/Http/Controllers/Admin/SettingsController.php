<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Payment\Services\PaymentService;
use App\Domain\Sms\Services\SmsService;
use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    /** Shows public grouped settings while never rendering encrypted secret values. */
    public function index(): View
    {
        return view('admin.settings.index', [
            'settings' => Setting::query()->where('is_secret', false)->orderBy('group')->orderBy('key')->get()->groupBy('group'),
        ]);
    }

    /** Persists storefront, provider, invoice, security and maintenance settings. */
    public function update(Request $request, SettingsRepository $settings): RedirectResponse
    {
        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:190'],
            'support_phone' => ['nullable', 'string', 'max:40'],
            'minimum_order' => ['nullable', 'integer', 'min:0'],
            'maintenance' => ['nullable', 'boolean'],
            'require_admin_2fa' => ['nullable', 'boolean'],
            'default_gateway' => ['required', Rule::in(PaymentService::ONLINE_GATEWAYS)],
            'default_sms_provider' => ['required', Rule::in(array_merge(['kavenegar'], SmsService::CONFIGURABLE_PROVIDERS))],
            'seller_name' => ['nullable', 'string', 'max:190'],
            'seller_national_id' => ['nullable', 'string', 'max:20'],
            'seller_economic_code' => ['nullable', 'string', 'max:30'],
            'seller_registration_number' => ['nullable', 'string', 'max:40'],
            'seller_phone' => ['nullable', 'string', 'max:40'],
            'seller_postal_code' => ['nullable', 'string', 'max:20'],
            'seller_address' => ['nullable', 'string', 'max:1000'],
        ]);
        $settings->set('site.name', $data['site_name']);
        $settings->set('site.support_phone', $data['support_phone'] ?? '');
        $settings->set('order.minimum', $data['minimum_order'] ?? 0, 'orders', 'int');
        $settings->set('site.maintenance', $data['maintenance'] ?? false, 'general', 'bool');
        $settings->set('security.require_admin_2fa', $data['require_admin_2fa'] ?? false, 'security', 'bool');
        $settings->set('payments.default_gateway', $data['default_gateway'], 'payments');
        $settings->set('sms.default_provider', $data['default_sms_provider'], 'sms');
        foreach (['seller_name', 'seller_national_id', 'seller_economic_code', 'seller_registration_number', 'seller_phone', 'seller_postal_code', 'seller_address'] as $field) {
            $settings->set('invoice.'.$field, $data[$field] ?? '', 'invoice');
        }

        return back()->with('success', 'تنظیمات ذخیره شد.');
    }
}
