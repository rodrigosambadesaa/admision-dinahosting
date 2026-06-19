<?php

declare(strict_types=1);

namespace App\Providers;

use DateTimeZone;

abstract class AbstractUtcRangeProvider
{
    protected function utc(): DateTimeZone
    {
        return new DateTimeZone('UTC');
    }
}
