<?php

namespace App\Domain\Sms\Contracts;

interface SmsProvider
{
    /** Returns the stable provider key stored in delivery logs. */
    public function name(): string;

    /** Sends a normal text message and returns provider message id plus raw response. */
    public function send(string $mobile, string $message, array $meta = []): array;

    /** Sends a provider-side pattern/template message when supported. */
    public function sendPattern(string $mobile, string $pattern, array $variables): array;

    /** Fetches delivery state when the provider exposes a status endpoint. */
    public function deliveryStatus(string $providerMessageId): array;
}
