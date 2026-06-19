<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\RangeProviderInterface;
use App\Model\DateRange;
use App\Model\RangeBoundary;
use DateTimeImmutable;
use InvalidArgumentException;

final class CustomRangeProvider extends AbstractUtcRangeProvider implements RangeProviderInterface
{
    private const FORMAT = 'Y-m-d H:i:s';
    private const BIGINT_PREFIX = 'ts:';

    public function __construct(
        private string $start,
        private string $end
    ) {
    }

    public function name(): string
    {
        return 'custom_range';
    }

    public function range(): DateRange
    {
        return new DateRange(
            $this->parseBoundary($this->start),
            $this->parseBoundary($this->end)
        );
    }

    private function parse(string $value): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat(self::FORMAT, $value, $this->utc());
        $errors = DateTimeImmutable::getLastErrors();

        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException(
                sprintf('Invalid date "%s". Expected format: %s in UTC.', $value, self::FORMAT)
            );
        }

        return $date;
    }

    private function isSyntheticTimestamp(string $value): bool
    {
        return str_starts_with($value, self::BIGINT_PREFIX);
    }

    private function parseBoundary(string $value): RangeBoundary
    {
        if ($this->isSyntheticTimestamp($value)) {
            $timestamp = $this->parseSyntheticTimestamp($value);

            return new RangeBoundary('ts:' . $timestamp, \App\Math\BigInteger::fromString($timestamp), 'synthetic-bigint');
        }

        $date = $this->parse($value);

        return new RangeBoundary(
            $date->format(self::FORMAT),
            \App\Math\BigInteger::fromInt($date->getTimestamp()),
            $date->getTimezone()->getName()
        );
    }

    private function parseSyntheticTimestamp(string $value): string
    {
        if (!preg_match('/^ts:([+-]?\d+)$/', $value, $matches)) {
            throw new InvalidArgumentException(
                sprintf('Invalid synthetic timestamp "%s". Expected format: ts:<bigint>.', $value)
            );
        }

        return $matches[1];
    }
}
