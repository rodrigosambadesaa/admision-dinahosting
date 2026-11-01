"use strict";

const { test } = require("node:test");
const assert = require("node:assert/strict");
const {
    BigInteger, FibonacciSequenceGenerator, DateRange, CurrentMonthRangeProvider,
    CurrentYearRangeProvider, CustomRangeProvider, FibonacciRangeResolver,
    parseUtcDateTime, parseRangeBoundary, sanitizeDateInput
} = require("../JAVASCRIPT/fibonacci.js");

test("BigInteger handles signed values and arbitrary-precision carry", () => {
    assert.equal(BigInteger.fromString("-000").toString(), "0");
    assert.equal(BigInteger.fromString("+00042").toString(), "42");
    assert.equal(BigInteger.fromString("99999999999999999999").add(BigInteger.one()).toString(), "100000000000000000000");
    assert.equal(BigInteger.fromString("-3").add(BigInteger.fromNumber(2)).toString(), "-1");
    assert.equal(BigInteger.fromNumber(Number.MAX_SAFE_INTEGER).toString(), "9007199254740991");
    assert.throws(() => BigInteger.fromNumber(Number.MAX_SAFE_INTEGER + 1));
    assert.throws(() => BigInteger.fromString("1e30"));
    assert.throws(() => BigInteger.fromString("9".repeat(257)));
    assert.equal(BigInteger.fromString("9".repeat(80) + "9").compare(BigInteger.fromString("9".repeat(80) + "8")), 1);
});

test("Fibonacci sequences, inclusive bounds and normalized reversed ranges", () => {
    const generator = new FibonacciSequenceGenerator();
    assert.deepEqual(generator.generateUpTo(BigInteger.fromNumber(13)).map(String),
        ["0", "1", "1", "2", "3", "5", "8", "13"]);
    assert.deepEqual(generator.generateUpTo(BigInteger.fromNumber(-1)), []);
    const resolver = new FibonacciRangeResolver(generator);
    for (const [start, end] of [["5", "13"], ["13", "5"]]) {
        const range = new DateRange(BigInteger.fromString(start), BigInteger.fromString(end), start, end);
        assert.deepEqual(resolver.resolve(range).map(String), ["5", "8", "13"]);
    }
    const hundred = "354224848179261915075";
    assert.deepEqual(resolver.resolve(new CustomRangeProvider("ts:" + hundred, "ts:" + hundred).getRange()).map(String), [hundred]);
});

test("UTC date parsing including 0000-0099, leap years, invalid dates", () => {
    assert.equal(parseUtcDateTime("1970-01-01 00:00:00").toString(), "0");
    assert.equal(parseUtcDateTime("0001-01-01 00:00:00").toString(), "-62135596800");
    assert.equal(parseUtcDateTime("0000-01-01 00:00:00").toString(), "-62167219200");
    assert.equal(parseUtcDateTime("2024-02-29 00:00:00").toString(), "1709164800");
    for (const date of ["2023-02-29 00:00:00", "2024-02-30 00:00:00",
        "2024-01-01 24:00:00", "2024-13-01 00:00:00", "2024-01-01T00:00:00"]) {
        assert.throws(() => parseUtcDateTime(date), date);
    }
    assert.equal(new CurrentMonthRangeProvider().getRange().timezone, "UTC");
    assert.equal(new CurrentYearRangeProvider().getRange().timezone, "UTC");
});

test("User input rejects oversized payloads and preserves synthetic bounds", () => {
    assert.equal(sanitizeDateInput(" ts:-0005 "), "ts:-0005");
    assert.equal(parseRangeBoundary("ts:+0013").timestamp.toString(), "13");
    assert.throws(() => sanitizeDateInput("ts:" + "9".repeat(257)));
    assert.throws(() => sanitizeDateInput("<img onerror=alert(1)>"));
    assert.throws(() => sanitizeDateInput(""));
    assert.equal(new CustomRangeProvider("ts:13", "ts:5").getRange().startTimestamp.toString(), "5");
});
