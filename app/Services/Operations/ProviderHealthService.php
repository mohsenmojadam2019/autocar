<?php

namespace App\Services\Operations;

use App\Domain\Payment\Services\PaymentService;
use App\Domain\Sms\Services\SmsService;
use App\Services\Settings\SettingsRepository;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Throwable;

class ProviderHealthService
{
    public function __construct(private readonly SettingsRepository $settings) {}

    /** Checks provider configuration/optional health URLs and stores latency/status history. */
    public function checkAll(): array
    {
        $results = [];
        foreach (PaymentService::ONLINE_GATEWAYS as $provider) {
            $results[] = $this->check('payment', $provider);
        }
        foreach (array_merge(['kavenegar'], SmsService::CONFIGURABLE_PROVIDERS) as $provider) {
            $results[] = $this->check('sms', $provider);
        }

        return $results;
    }

    /** Checks one provider without making a billable payment/SMS transaction. */
    public function check(string $type, string $provider): array
    {
        $started = microtime(true);
        $status = 'configured';
        $message = null;
        try {
            $configured = $this->configured($type, $provider);
            if (! $configured) {
                $status = 'misconfigured';
                $message = 'تنظیمات ضروری کامل نیست.';
            } elseif ($healthUrl = $this->settings->get($type === 'payment' ? 'payments.'.$provider.'.health_url' : 'sms.'.$provider.'.health_url')) {
                $response = Http::timeout(8)->get((string) $healthUrl);
                $status = $response->successful() ? 'healthy' : 'degraded';
                $message = 'HTTP '.$response->status();
            }
        } catch (Throwable $exception) {
            $status = 'failed';
            $message = mb_substr($exception->getMessage(), 0, 500);
        }
        $latency = (int) round((microtime(true) - $started) * 1000);
        DB::table('provider_health_checks')->insert(['provider_type' => $type, 'provider_name' => $provider, 'status' => $status, 'latency_ms' => $latency, 'message' => $message, 'checked_at' => now()]);

        return compact('type', 'provider', 'status', 'latency', 'message');
    }

    /** Validates only the minimum credential/endpoint set needed by each adapter. */
    private function configured(string $type, string $provider): bool
    {
        if ($type === 'payment') {
            if ($provider === 'zarinpal') {
                return (string) $this->settings->get('payments.zarinpal.merchant_id', '') !== '';
            }
            $base = 'payments.'.$provider.'.';
            return (string) $this->settings->get($base.'request_url', '') !== ''
                && (string) $this->settings->get($base.'verify_url', '') !== ''
                && (string) $this->settings->get($base.'redirect_url', '') !== ''
                && (string) $this->settings->get($base.'api_key', '') !== '';
        }
        if ($provider === 'kavenegar') {
            return (string) $this->settings->get('sms.kavenegar.api_key', '') !== '';
        }
        $base = 'sms.'.$provider.'.';
        return (string) $this->settings->get($base.'send_url', '') !== '' && (string) $this->settings->get($base.'api_key', '') !== '';
    }
}
