<?php

declare(strict_types=1);

namespace App\Console;

use App\Contracts\RangeProviderInterface;
use App\Providers\CurrentMonthRangeProvider;
use App\Providers\CurrentYearRangeProvider;
use App\Providers\CustomRangeProvider;
use App\Service\FibonacciRangeResolver;
use App\Service\FibonacciSequenceGenerator;
use InvalidArgumentException;
use Throwable;

final class Application
{
    public function run(array $argv): void
    {
        try {
            $providers = $this->buildProviders($argv);
            $resolver = new FibonacciRangeResolver(new FibonacciSequenceGenerator());

            $result = [];

            foreach ($providers as $provider) {
                $range = $provider->range();

                $result[$provider->name()] = [
                    'range' => $range->toArray(),
                    'fibonacci_timestamps' => $resolver->resolve($range),
                ];
            }

            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
        } catch (Throwable $exception) {
            fwrite(STDERR, $exception->getMessage() . PHP_EOL);
            fwrite(STDERR, $this->usage());
            exit(1);
        }
    }

    /**
     * @return RangeProviderInterface[]
     */
    private function buildProviders(array $argv): array
    {
        if (count($argv) !== 3) {
            throw new InvalidArgumentException('Two custom dates are required.');
        }

        foreach ([$argv[1], $argv[2]] as $argument) {
            if (!is_string($argument) || $argument === '') {
                throw new InvalidArgumentException('Each custom range value must be a non-empty string.');
            }
        }

        return [
            new CurrentMonthRangeProvider(),
            new CurrentYearRangeProvider(),
            new CustomRangeProvider($argv[1], $argv[2]),
        ];
    }

    private function usage(): string
    {
        return 'Usage: php PHP/fibonacci.php "2026-06-01 00:00:00" "2026-06-30 23:59:59"' . PHP_EOL
            . '   or: php PHP/fibonacci.php "ts:12345678901234567890" "ts:12345678901234567999"' . PHP_EOL;
    }
}
