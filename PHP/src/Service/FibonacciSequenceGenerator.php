<?php

declare(strict_types=1);

namespace App\Service;

use App\Math\BigInteger;

final class FibonacciSequenceGenerator
{
    public function generateUpTo(BigInteger $limit): array
    {
        if ($limit->isNegative()) {
            return [];
        }

        $zero = BigInteger::fromInt(0);
        $one = BigInteger::fromInt(1);
        $sequence = [$zero];

        if ($limit->compare($zero) === 0) {
            return $sequence;
        }

        $sequence[] = $one;
        $previous = $zero;
        $current = $one;

        while (true) {
            $next = $previous->add($current);

            if ($next->compare($limit) > 0) {
                break;
            }

            $sequence[] = $next;
            $previous = $current;
            $current = $next;
        }

        return $sequence;
    }
}
