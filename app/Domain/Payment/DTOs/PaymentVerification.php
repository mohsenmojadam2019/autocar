<?php

namespace App\Domain\Payment\DTOs;

final readonly class PaymentVerification
{
    public function __construct(public bool $successful, public ?string $referenceId = null, public array $payload = [], public ?string $message = null) {}
}
