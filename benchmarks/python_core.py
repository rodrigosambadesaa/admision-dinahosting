"""Isolated hot-core benchmark of the unchanged Django Fibonacci service."""
import hashlib
import json
import os
from pathlib import Path
import sys
from time import process_time_ns

django_root = Path(os.environ['BENCH_DJANGO_ROOT']).resolve()
sys.path.insert(0, str(django_root))
from challenge.services import CustomRangeProvider, FibonacciRangeResolver  # noqa: E402

if len(sys.argv) != 4:
    raise SystemExit("Usage: python python_core.py N start end")
iterations, start, end = int(sys.argv[1]), sys.argv[2], sys.argv[3]
if iterations < 0:
    raise ValueError("iterations must be non-negative")
range_ = CustomRangeProvider(start, end).get_range()
resolver = FibonacciRangeResolver()
values = []
if iterations:
    resolver.resolve(range_)  # Excluded warmup, same as other runtimes.
before = process_time_ns()
for _ in range(iterations):
    values = resolver.resolve(range_)
elapsed_ms = (process_time_ns() - before) / 1_000_000
strings = [str(value) for value in values]
print(json.dumps({
    "count": len(strings),
    "first": strings[0] if strings else None,
    "last": strings[-1] if strings else None,
    "sha256": hashlib.sha256(",".join(strings).encode()).hexdigest(),
    "cpu_ms": elapsed_ms,
}))
