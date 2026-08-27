<?php

namespace App\Domain\Catalog\Enums;

enum ProductStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case OutOfStock = 'out_of_stock';
    case Discontinued = 'discontinued';
    case Archived = 'archived';
}
