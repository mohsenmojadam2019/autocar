<?php

namespace App\Domain\Shared\ValueObjects;

use InvalidArgumentException;

final readonly class Money
{
    /** Creates a non-negative IRR monetary value stored in the smallest integer unit. */
    public function __construct(public int $amount)
    {
        if ($amount < 0) {
            throw new InvalidArgumentException('Money amount cannot be negative.');
        }
    }

    /** Adds two monetary values without floating-point arithmetic. */
    public function add(self $other): self
    {
        return new self($this->amount + $other->amount);
    }

    /** Returns the value formatted for Persian storefront output. */
    public function formatted(): string
    {
        return number_format($this->amount).' ریال';
    }
}
