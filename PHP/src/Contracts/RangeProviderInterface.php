<?php

declare(strict_types=1);

namespace App\Contracts;

use App\Model\DateRange;

interface RangeProviderInterface
{
    public function name(): string;

    public function range(): DateRange;
}
