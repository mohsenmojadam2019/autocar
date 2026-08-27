<?php

namespace App\Domain\Vehicle\DTOs;

use App\Domain\Vehicle\Enums\FitmentStatus;

final readonly class FitmentResult
{
    public function __construct(
        public FitmentStatus $status,
        public string $message,
        public ?int $matchedRuleId = null,
        public int $confidence = 0,
    ) {}

    /** Indicates whether the result permits a confident compatible badge in the storefront. */
    public function isCompatible(): bool
    {
        return $this->status === FitmentStatus::Compatible;
    }
}
