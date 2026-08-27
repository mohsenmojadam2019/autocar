<?php

namespace Tests\Unit;

use App\Domain\Vehicle\DTOs\FitmentResult;
use App\Domain\Vehicle\Enums\FitmentStatus;
use PHPUnit\Framework\TestCase;

class FitmentResultTest extends TestCase
{
    /** Verifies that only an explicit compatible state enables the positive storefront badge. */
    public function test_compatible_flag_is_strict(): void
    {
        $compatible = new FitmentResult(FitmentStatus::Compatible, 'ok');
        $conditional = new FitmentResult(FitmentStatus::Conditional, 'check');

        self::assertTrue($compatible->isCompatible());
        self::assertFalse($conditional->isCompatible());
    }
}
