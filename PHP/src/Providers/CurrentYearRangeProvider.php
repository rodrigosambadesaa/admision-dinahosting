<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\RangeProviderInterface;
use App\Model\DateRange;
use DateTimeImmutable;

final class CurrentYearRangeProvider extends AbstractUtcRangeProvider implements RangeProviderInterface
{
    public function name(): string
    {
        return 'current_year';
    }

    public function range(): DateRange
    {
        $now = new DateTimeImmutable('now', $this->utc());
        $year = $now->format('Y');

        $start = new DateTimeImmutable($year . '-01-01 00:00:00', $this->utc());
        $end = new DateTimeImmutable($year . '-12-31 23:59:59', $this->utc());

        return DateRange::fromDateTimes($start, $end);
    }
}
