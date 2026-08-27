<?php

namespace App\Domain\Catalog\Enums;

enum AuthenticityType: string
{
    case Genuine = 'genuine';
    case Oem = 'oem';
    case Company = 'company';
    case Imported = 'imported';
    case Economy = 'economy';
}
