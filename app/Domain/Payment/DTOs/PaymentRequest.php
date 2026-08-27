<?php

namespace App\Domain\Payment\DTOs;

final readonly class PaymentRequest
{
    public function __construct(public bool $successful, public ?string $authority = null, public ?string $redirectUrl = null, public array $payload = [], public ?string $message = null) {}
}
