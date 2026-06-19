<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\RangeProviderInterface;
use App\Model\DateRange;
use DateTimeImmutable;

final class CurrentMonthRangeProvider extends AbstractUtcRangeProvider implements RangeProviderInterface
{
    public function name(): string
    {
        return 'current_month';
    }

    public function range(): DateRange
    {
        $now = new DateTimeImmutable('now', $this->utc());

        $start = $now->modify('first day of this month')->setTime(0, 0, 0);
        $end = $now->modify('last day of this month')->setTime(23, 59, 59);

        return DateRange::fromDateTimes($start, $end);
    }
}
