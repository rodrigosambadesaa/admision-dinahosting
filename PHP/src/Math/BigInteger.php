<?php

declare(strict_types=1);

namespace App\Math;

use InvalidArgumentException;

final class BigInteger
{
    private string $value;

    private function __construct(string $value)
    {
        $this->value = $this->normalize($value);
    }

    public static function fromInt(int $value): self
    {
        return new self((string) $value);
    }

    public static function fromString(string $value): self
    {
        if (!preg_match('/^[+-]?\d+$/', $value)) {
            throw new InvalidArgumentException(sprintf('Invalid integer value "%s".', $value));
        }

        return new self($value);
    }

    public function add(self $other): self
    {
        [$leftSign, $leftAbs] = $this->parts();
        [$rightSign, $rightAbs] = $other->parts();

        if ($leftSign === $rightSign) {
            $sum = self::addAbsolute($leftAbs, $rightAbs);

            return new self($leftSign < 0 ? '-' . $sum : $sum);
        }

        $comparison = self::compareAbsolute($leftAbs, $rightAbs);

        if ($comparison === 0) {
            return new self('0');
        }

        if ($comparison > 0) {
            $difference = self::subtractAbsolute($leftAbs, $rightAbs);

            return new self($leftSign < 0 ? '-' . $difference : $difference);
        }

        $difference = self::subtractAbsolute($rightAbs, $leftAbs);

        return new self($rightSign < 0 ? '-' . $difference : $difference);
    }

    public function compare(self $other): int
    {
        [$leftSign, $leftAbs] = $this->parts();
        [$rightSign, $rightAbs] = $other->parts();

        if ($leftSign !== $rightSign) {
            return $leftSign <=> $rightSign;
        }

        $comparison = self::compareAbsolute($leftAbs, $rightAbs);

        return $leftSign < 0 ? -$comparison : $comparison;
    }

    public function isNegative(): bool
    {
        return str_starts_with($this->value, '-');
    }

    public function __toString(): string
    {
        return $this->value;
    }

    private function normalize(string $value): string
    {
        $sign = '';

        if ($value[0] === '+' || $value[0] === '-') {
            $sign = $value[0] === '-' ? '-' : '';
            $value = substr($value, 1);
        }

        $value = ltrim($value, '0');

        if ($value === '') {
            return '0';
        }

        return $sign . $value;
    }

    /**
     * @return array{int, string}
     */
    private function parts(): array
    {
        if ($this->isNegative()) {
            return [-1, substr($this->value, 1)];
        }

        return [1, $this->value];
    }

    private static function compareAbsolute(string $left, string $right): int
    {
        $lengthComparison = strlen($left) <=> strlen($right);

        if ($lengthComparison !== 0) {
            return $lengthComparison;
        }

        return $left <=> $right;
    }

    private static function addAbsolute(string $left, string $right): string
    {
        $carry = 0;
        $result = '';
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;

        while ($leftIndex >= 0 || $rightIndex >= 0 || $carry > 0) {
            $leftDigit = $leftIndex >= 0 ? (int) $left[$leftIndex] : 0;
            $rightDigit = $rightIndex >= 0 ? (int) $right[$rightIndex] : 0;
            $sum = $leftDigit + $rightDigit + $carry;

            $result = ($sum % 10) . $result;
            $carry = intdiv($sum, 10);
            $leftIndex--;
            $rightIndex--;
        }

        return $result;
    }

    private static function subtractAbsolute(string $left, string $right): string
    {
        $borrow = 0;
        $result = '';
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;

        while ($leftIndex >= 0) {
            $leftDigit = (int) $left[$leftIndex] - $borrow;
            $rightDigit = $rightIndex >= 0 ? (int) $right[$rightIndex] : 0;

            if ($leftDigit < $rightDigit) {
                $leftDigit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $result = ($leftDigit - $rightDigit) . $result;
            $leftIndex--;
            $rightIndex--;
        }

        return ltrim($result, '0') ?: '0';
    }
}
