<?php

namespace App\Domain\Shipping\Services;

use App\Domain\Order\Models\Order;
use App\Domain\Shipping\Contracts\ShippingProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class ConfigurableShippingProvider implements ShippingProvider
{
    public function __construct(
        private readonly string $provider,
        private readonly array $config,
    ) {}

    public function name(): string
    {
        return $this->provider;
    }

    public function createShipment(Order $order, array $payload = []): array
    {
        if (($this->config['mode'] ?? 'manual') === 'manual') {
            return [
                'provider' => $this->provider,
                'tracking_code' => $payload['tracking_code'] ?? null,
                'label_url' => null,
                'status' => 'preparing',
                'payload' => ['mode' => 'manual'],
            ];
        }

        $url = $this->endpoint('create_endpoint');
        $body = array_merge([
            'order_number' => $order->number,
            'amount' => (int) $order->grand_total,
            'address' => $order->shipping_address,
        ], $payload);
        $response = $this->request()->post($url, $body);
        if (! $response->successful()) {
            throw new RuntimeException("{$this->provider} shipment request failed with HTTP {$response->status()}.");
        }
        $data = $response->json();

        return [
            'provider' => $this->provider,
            'tracking_code' => data_get($data, $this->config['tracking_path'] ?? 'tracking_code'),
            'label_url' => data_get($data, $this->config['label_path'] ?? 'label_url'),
            'status' => data_get($data, $this->config['status_path'] ?? 'status', 'preparing'),
            'payload' => $data,
        ];
    }

    public function track(string $trackingCode): array
    {
        if (($this->config['mode'] ?? 'manual') === 'manual') {
            return ['provider' => $this->provider, 'tracking_code' => $trackingCode, 'status' => 'unknown', 'payload' => ['mode' => 'manual']];
        }

        $template = $this->endpoint('track_endpoint');
        $url = str_replace('{tracking_code}', urlencode($trackingCode), $template);
        $response = $this->request()->get($url);
        if (! $response->successful()) {
            throw new RuntimeException("{$this->provider} tracking request failed with HTTP {$response->status()}.");
        }
        $data = $response->json();

        return [
            'provider' => $this->provider,
            'tracking_code' => $trackingCode,
            'status' => data_get($data, $this->config['status_path'] ?? 'status', 'unknown'),
            'location' => data_get($data, $this->config['location_path'] ?? 'location'),
            'description' => data_get($data, $this->config['description_path'] ?? 'description'),
            'payload' => $data,
        ];
    }

    private function request(): \Illuminate\Http\Client\PendingRequest
    {
        $request = Http::acceptJson()->asJson()->timeout((int) ($this->config['timeout'] ?? 15))->retry(2, 250, throw: false);
        if ($token = ($this->config['token'] ?? null)) {
            $request = $request->withToken($token);
        }
        if ($apiKey = ($this->config['api_key'] ?? null)) {
            $request = $request->withHeaders([($this->config['api_key_header'] ?? 'X-API-Key') => $apiKey]);
        }

        return $request;
    }

    private function endpoint(string $key): string
    {
        $base = rtrim((string) ($this->config['base_url'] ?? ''), '/');
        $path = (string) ($this->config[$key] ?? '');
        if ($base === '' || $path === '') {
            throw new RuntimeException("{$this->provider} provider endpoint is not configured.");
        }

        return str_starts_with($path, 'http') ? $path : $base.'/'.ltrim($path, '/');
    }
}
