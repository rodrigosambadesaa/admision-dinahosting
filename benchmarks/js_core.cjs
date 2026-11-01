'use strict';

const crypto = require('node:crypto');
const path = require('node:path');
const original = process.env.BENCH_ORIGINAL_ROOT;
if (!original) throw new Error('BENCH_ORIGINAL_ROOT is required');
const { CustomRangeProvider, FibonacciRangeResolver, FibonacciSequenceGenerator } =
    require(path.join(original, 'JAVASCRIPT', 'fibonacci.js'));
const [iterationsInput, start, end] = process.argv.slice(2);
const iterations = Number(iterationsInput);
if (!Number.isSafeInteger(iterations) || iterations < 0 || !start || !end) {
    throw new Error('Usage: node js_core.cjs N start end');
}
const range = new CustomRangeProvider(start, end).getRange();
const resolver = new FibonacciRangeResolver(new FibonacciSequenceGenerator());
let values = [];
if (iterations) resolver.resolve(range); // JIT warmup, excluded from CPU timing.
const before = process.cpuUsage();
for (let i = 0; i < iterations; i++) {
    values = resolver.resolve(range);
}
const delta = process.cpuUsage(before);
const strings = values.map(v => v.toString());
console.log(JSON.stringify({
    count: strings.length,
    first: strings[0] ?? null,
    last: strings[strings.length - 1] ?? null,
    sha256: crypto.createHash('sha256').update(strings.join(',')).digest('hex'),
    cpu_ms: (delta.user + delta.system) / 1000,
}));
