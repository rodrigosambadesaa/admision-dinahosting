<?php
declare(strict_types=1);
require dirname(__DIR__) . '/PHP/src/autoload.php';

use App\Providers\CustomRangeProvider;
use App\Service\FibonacciRangeResolver;
use App\Service\FibonacciSequenceGenerator;

if ($argc !== 4) {
    fwrite(STDERR, "Usage: php php_core.php N start end\n");
    exit(2);
}

$iterations = (int) $argv[1];
$range = (new CustomRangeProvider($argv[2], $argv[3]))->range();
$resolver = new FibonacciRangeResolver(new FibonacciSequenceGenerator());
$values = [];
if ($iterations > 0) {
    $resolver->resolve($range); // Excluded warmup for equivalent hot-path measurement.
}
$before = getrusage();
for ($i = 0; $i < $iterations; ++$i) {
    $values = $resolver->resolve($range);
}
$after = getrusage();
$micros = static fn(array $r): int => $r['ru_utime.tv_sec'] * 1000000
    + $r['ru_utime.tv_usec'] + $r['ru_stime.tv_sec'] * 1000000 + $r['ru_stime.tv_usec'];
echo json_encode([
    'count' => count($values),
    'first' => $values[0] ?? null,
    'last' => count($values) ? $values[count($values) - 1] : null,
    'sha256' => hash('sha256', implode(',', $values)),
    'cpu_ms' => ($micros($after) - $micros($before)) / 1000.0,
], JSON_THROW_ON_ERROR), PHP_EOL;
