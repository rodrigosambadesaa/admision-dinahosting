<?php

declare(strict_types=1);

namespace App\Service;

use App\Model\DateRange;

final class FibonacciRangeResolver
{
    public function __construct(private FibonacciSequenceGenerator $generator)
    {
    }

    public function resolve(DateRange $range): array
    {
        $sequence = $this->generator->generateUpTo($range->endTimestamp());

        return array_values(array_map(
            static fn ($value): string => (string) $value,
            array_filter(
                $sequence,
                static fn ($value): bool =>
                    $value->compare($range->startTimestamp()) >= 0
                    && $value->compare($range->endTimestamp()) <= 0
            )
        ));
    }
}
