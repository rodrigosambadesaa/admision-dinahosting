<?php

declare(strict_types=1);

require __DIR__ . '/../PHP/src/autoload.php';

use App\Math\BigInteger;
use App\Model\DateRange;
use App\Providers\CustomRangeProvider;
use App\Providers\CurrentMonthRangeProvider;
use App\Providers\CurrentYearRangeProvider;
use App\Service\FibonacciRangeResolver;
use App\Service\FibonacciSequenceGenerator;

function check(bool $value, string $message): void
{
    if (!$value) {
        throw new RuntimeException($message);
    }
}

function same(mixed $expected, mixed $actual, string $message): void
{
    check($expected === $actual, $message . ': expected ' . var_export($expected, true)
        . ', got ' . var_export($actual, true));
}

function throws(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (InvalidArgumentException $exception) {
        return;
    }
    throw new RuntimeException($message . ': expected InvalidArgumentException');
}

$cases = [
    'BigInteger normalization' => static function (): void {
        same('0', (string) BigInteger::fromString('-0000'), 'negative zero');
        same('42', (string) BigInteger::fromString('+00042'), 'leading zeros');
        same('-2', (string) BigInteger::fromString('-0002'), 'negative normalization');
        throws(static fn () => BigInteger::fromString('1e3'), 'scientific notation rejected');
        throws(static fn () => BigInteger::fromString(str_repeat('9', 257)), 'length limit');
        same('100000000000000000000', (string) BigInteger::fromString('99999999999999999999')
            ->add(BigInteger::fromInt(1)), 'large carry');
        same('-1', (string) BigInteger::fromString('-3')->add(BigInteger::fromInt(2)), 'signed sum');
        same(0, BigInteger::fromString('-000')->compare(BigInteger::fromInt(0)), 'zero comparison');
    },
    'Fibonacci sequences and ranges' => static function (): void {
        $generator = new FibonacciSequenceGenerator();
        $values = array_map('strval', $generator->generateUpTo(BigInteger::fromInt(13)));
        same(['0', '1', '1', '2', '3', '5', '8', '13'], $values, 'Fibonacci to 13');
        same([], $generator->generateUpTo(BigInteger::fromInt(-1)), 'negative limit');
        same(['0'], array_map('strval', $generator->generateUpTo(BigInteger::fromInt(0))), 'zero limit');
        $resolver = new FibonacciRangeResolver($generator);
        same(['5', '8', '13'], $resolver->resolve(DateRange::fromTimestampStrings('5', '13')), 'range');
        same(['5', '8', '13'], $resolver->resolve(DateRange::fromTimestampStrings('13', '5')), 'reversed range');
        $large = '354224848179261915075'; // F(100)
        same([$large], $resolver->resolve(DateRange::fromTimestampStrings($large, $large)), 'large F(100)');
        same([], $resolver->resolve(DateRange::fromTimestampStrings('-50', '-1')), 'all negative');
    },
    'Strict UTC dates' => static function (): void {
        $provider = new CustomRangeProvider('2024-02-29 00:00:00', '2024-02-29 23:59:59');
        $range = $provider->range()->toArray();
        same('UTC', $range['timezone'], 'UTC timezone');
        same('2024-02-29 00:00:00', $range['start'], 'valid leap day');
        foreach (['2023-02-29 00:00:00', '2024-02-30 00:00:00',
                  '2024-01-01 24:00:00', '2024-1-01 00:00:00',
                  '2024-01-01T00:00:00', '2024-01-01 00:00:00 junk'] as $invalid) {
            throws(static fn () => (new CustomRangeProvider($invalid, '2024-02-29 00:00:00'))->range(), $invalid);
        }
        throws(static fn () => (new CustomRangeProvider('ts:' . str_repeat('9', 257), 'ts:0'))->range(),
            'synthetic input length');
        $swapped = (new CustomRangeProvider('ts:13', 'ts:5'))->range()->toArray();
        same('5', $swapped['start_timestamp'], 'normalized start');
        same('13', $swapped['end_timestamp'], 'normalized end');
        check((new CurrentMonthRangeProvider())->range()->startTimestamp()
            ->compare((new CurrentMonthRangeProvider())->range()->endTimestamp()) <= 0, 'month range');
        check((new CurrentYearRangeProvider())->range()->startTimestamp()
            ->compare((new CurrentYearRangeProvider())->range()->endTimestamp()) <= 0, 'year range');
    },
    'CLI JSON contract' => static function (): void {
        $cmd = escapeshellarg(PHP_BINARY) . ' '
            . escapeshellarg(__DIR__ . '/../PHP/fibonacci.php') . ' '
            . escapeshellarg('ts:5') . ' ' . escapeshellarg('ts:13');
        $output = [];
        $code = 0;
        exec($cmd, $output, $code);
        same(0, $code, 'CLI exit code');
        $json = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
        same(['5', '8', '13'], $json['custom_range']['fibonacci_timestamps'], 'CLI output');
        check(isset($json['current_month'], $json['current_year']), 'required groups');
    },
];

foreach ($cases as $name => $test) {
    $test();
    echo 'PASS ' . $name . PHP_EOL;
}
echo count($cases) . ' PHP test groups passed.' . PHP_EOL;
