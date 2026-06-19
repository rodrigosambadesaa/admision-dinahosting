<?php

declare(strict_types=1);

namespace App\Model;

use DateTimeImmutable;
use App\Math\BigInteger;

final class DateRange
{
    public function __construct(
        private RangeBoundary $start,
        private RangeBoundary $end
    ) {
        if ($this->start->timestamp()->compare($this->end->timestamp()) > 0) {
            [$this->start, $this->end] = [$this->end, $this->start];
        }
    }

    public static function fromDateTimes(DateTimeImmutable $start, DateTimeImmutable $end): self
    {
        return new self(
            new RangeBoundary(
                $start->format('Y-m-d H:i:s'),
                BigInteger::fromInt($start->getTimestamp()),
                $start->getTimezone()->getName()
            ),
            new RangeBoundary(
                $end->format('Y-m-d H:i:s'),
                BigInteger::fromInt($end->getTimestamp()),
                $end->getTimezone()->getName()
            )
        );
    }

    public static function fromTimestampStrings(string $start, string $end): self
    {
        return new self(
            new RangeBoundary('ts:' . $start, BigInteger::fromString($start), 'synthetic-bigint'),
            new RangeBoundary('ts:' . $end, BigInteger::fromString($end), 'synthetic-bigint')
        );
    }

    public function startTimestamp(): BigInteger
    {
        return $this->start->timestamp();
    }

    public function endTimestamp(): BigInteger
    {
        return $this->end->timestamp();
    }

    public function toArray(): array
    {
        return [
            'start' => $this->start->label(),
            'end' => $this->end->label(),
            'timezone' => $this->start->timezone() === $this->end->timezone()
                ? $this->start->timezone()
                : $this->start->timezone() . ' -> ' . $this->end->timezone(),
            'start_timestamp' => (string) $this->startTimestamp(),
            'end_timestamp' => (string) $this->endTimestamp(),
        ];
    }
}
