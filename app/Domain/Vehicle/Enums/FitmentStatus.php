<?php

namespace App\Domain\Vehicle\Enums;

enum FitmentStatus: string
{
    case Compatible = 'compatible';
    case Conditional = 'conditional';
    case Incompatible = 'incompatible';
}
