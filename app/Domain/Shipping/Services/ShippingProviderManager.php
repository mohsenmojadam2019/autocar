<?php

namespace App\Domain\Shipping\Services;

use App\Domain\Shipping\Contracts\ShippingProvider;
use App\Services\Settings\SettingsRepository;
use InvalidArgumentException;

class ShippingProviderManager
{
    public const PROVIDERS = ['post', 'tipax', 'courier', 'pickup'];

    public function __construct(private readonly SettingsRepository $settings) {}

    /** Resolves Post, Tipax, local courier or pickup through one normalized carrier contract. */
    public function driver(string $provider): ShippingProvider
    {
        $provider = strtolower($provider);
        if (! in_array($provider, self::PROVIDERS, true)) {
            throw new InvalidArgumentException('Shipping provider is not supported: '.$provider);
        }

        return new ConfigurableShippingProvider($provider, [
            'mode' => $this->settings->get("shipping.providers.{$provider}.mode", 'manual'),
            'base_url' => $this->settings->get("shipping.providers.{$provider}.base_url"),
            'create_endpoint' => $this->settings->get("shipping.providers.{$provider}.create_endpoint"),
            'track_endpoint' => $this->settings->get("shipping.providers.{$provider}.track_endpoint"),
            'token' => $this->settings->get("shipping.providers.{$provider}.token"),
            'api_key' => $this->settings->get("shipping.providers.{$provider}.api_key"),
            'api_key_header' => $this->settings->get("shipping.providers.{$provider}.api_key_header", 'X-API-Key'),
            'tracking_path' => $this->settings->get("shipping.providers.{$provider}.tracking_path", 'tracking_code'),
            'label_path' => $this->settings->get("shipping.providers.{$provider}.label_path", 'label_url'),
            'status_path' => $this->settings->get("shipping.providers.{$provider}.status_path", 'status'),
            'location_path' => $this->settings->get("shipping.providers.{$provider}.location_path", 'location'),
            'description_path' => $this->settings->get("shipping.providers.{$provider}.description_path", 'description'),
            'timeout' => (int) $this->settings->get("shipping.providers.{$provider}.timeout", 15),
        ]);
    }
}
