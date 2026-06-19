<?php

declare(strict_types=1);

namespace App\Model;

use App\Math\BigInteger;

final class RangeBoundary
{
    public function __construct(
        private string $label,
        private BigInteger $timestamp,
        private string $timezone
    ) {
    }

    public function label(): string
    {
        return $this->label;
    }

    public function timestamp(): BigInteger
    {
        return $this->timestamp;
    }

    public function timezone(): string
    {
        return $this->timezone;
    }
}
