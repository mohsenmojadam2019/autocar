<?php

namespace App\Domain\Sms\Providers;

use App\Domain\Sms\Contracts\SmsProvider;
use App\Services\Settings\SettingsRepository;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ConfigurableSmsProvider implements SmsProvider
{
    public function __construct(private readonly SettingsRepository $settings, private readonly string $providerName) {}

    /** Returns one of the configured adapter keys such as smsir, ippanel or ghasedak. */
    public function name(): string
    {
        return $this->providerName;
    }

    /** Sends a normal message using configurable endpoint/header/payload field names. */
    public function send(string $mobile, string $message, array $meta = []): array
    {
        $base = 'sms.'.$this->providerName.'.';
        $endpoint = $this->required($base.'send_url');
        $payload = [
            (string) $this->settings->get($base.'mobile_field', 'mobile') => $mobile,
            (string) $this->settings->get($base.'message_field', 'message') => $message,
        ];
        if ($sender = $this->settings->get($base.'sender')) {
            $payload[(string) $this->settings->get($base.'sender_field', 'sender')] = $sender;
        }
        $response = $this->client($base)->post($endpoint, array_merge($payload, $meta));
        $data = $response->json() ?: [];
        if (! $response->successful()) {
            throw new RuntimeException('ارسال پیامک '.$this->providerName.' ناموفق بود.');
        }

        return ['id' => (string) data_get($data, $this->settings->get($base.'message_id_path', 'id'), ''), 'response' => $data];
    }

    /** Sends a provider template/pattern through a separately configurable endpoint. */
    public function sendPattern(string $mobile, string $pattern, array $variables): array
    {
        $base = 'sms.'.$this->providerName.'.';
        $endpoint = $this->required($base.'pattern_url');
        $response = $this->client($base)->post($endpoint, [
            (string) $this->settings->get($base.'mobile_field', 'mobile') => $mobile,
            (string) $this->settings->get($base.'pattern_field', 'template') => $pattern,
            (string) $this->settings->get($base.'variables_field', 'variables') => $variables,
        ]);
        $data = $response->json() ?: [];
        if (! $response->successful()) {
            throw new RuntimeException('ارسال الگوی '.$this->providerName.' ناموفق بود.');
        }

        return ['id' => (string) data_get($data, $this->settings->get($base.'message_id_path', 'id'), ''), 'response' => $data];
    }

    /** Queries an optional delivery endpoint; providers without one return unknown safely. */
    public function deliveryStatus(string $providerMessageId): array
    {
        $base = 'sms.'.$this->providerName.'.';
        $endpoint = $this->settings->get($base.'status_url');
        if (! $endpoint) {
            return ['status' => 'unknown', 'response' => [], 'successful' => false];
        }
        $response = $this->client($base)->get(str_replace('{id}', urlencode($providerMessageId), (string) $endpoint));
        $data = $response->json() ?: [];

        return [
            'status' => data_get($data, $this->settings->get($base.'status_path', 'status'), 'unknown'),
            'response' => $data,
            'successful' => $response->successful(),
        ];
    }

    /** Builds an authenticated HTTP client without exposing secret values to callers. */
    private function client(string $base): PendingRequest
    {
        $client = Http::timeout(15)->acceptJson();
        $apiKey = $this->settings->get($base.'api_key');
        if ($apiKey) {
            $header = (string) $this->settings->get($base.'api_key_header', 'Authorization');
            $prefix = (string) $this->settings->get($base.'api_key_prefix', '');
            $client = $client->withHeader($header, $prefix.$apiKey);
        }

        return $client;
    }

    /** Reads one required provider setting or throws a safe configuration error. */
    private function required(string $key): string
    {
        $value = (string) $this->settings->get($key, '');
        if ($value === '') {
            throw new RuntimeException('تنظیمات '.$this->providerName.' کامل نیست.');
        }

        return $value;
    }
}
