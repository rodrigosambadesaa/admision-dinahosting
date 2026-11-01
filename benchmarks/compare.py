#!/usr/bin/env python3
"""Cross-runtime reproducible CPU and peak RSS measurement on a single VM.

Core: unchanged range resolvers in a warm process. CPU timing from each runtime.
Peak RSS is for the entire process, including interpreter, imports and result.
CLI: independent actual PHP and Django CLI invocations, including startup.
Node is browser-based in the original and has no equivalent CLI.
"""
import argparse
from datetime import datetime, timezone
import json
import os
from pathlib import Path
import platform
import random
import shutil
import statistics
import subprocess
import sys
import tempfile

SAMPLES = 5
CASE_DEFS = (
    ("small_0_21", "ts:0", "ts:21", 5000),
    ("calendar_1970_2024", "1970-01-01 00:00:00", "2024-12-31 23:59:59", 1200),
    ("big_80_digits", "ts:0", "ts:" + "9" * 80, 90),
    ("big_250_digits", "ts:0", "ts:" + "9" * 250, 8),
)
CLI_CASES = (CASE_DEFS[0], CASE_DEFS[3])
RUNTIMES = ("php", "javascript", "python")
TIME_BIN = "/usr/bin/time"


def median(values):
    return statistics.median(values)


def timed(cmd, env, cwd):
    """Separate peak resident size per process via GNU time."""
    with tempfile.NamedTemporaryFile(prefix="fibonacci-time-", delete=False) as handle:
        report_path = Path(handle.name)
    try:
        result = subprocess.run(
            [TIME_BIN, "-f", "%U %S %M %e", "-o", str(report_path), *cmd],
            cwd=str(cwd), env=env, capture_output=True, text=True,
            timeout=75, check=False,
        )
        if result.returncode != 0:
            raise RuntimeError(
                f"Command {cmd[0]} exited {result.returncode}\n"
                f"stderr: {result.stderr[-3500:]}\nstdout: {result.stdout[-500:]}"
            )
        fields = report_path.read_text().strip().split()
        if len(fields) != 4:
            raise RuntimeError(f"Unexpected GNU time output: {fields}")
        user_s, system_s, rss_kib, wall_s = map(float, fields)
        return {
            "payload": json.loads(result.stdout),
            "rss_kib": int(rss_kib),
            "external_cpu_ms": (user_s + system_s) * 1000,
            "wall_ms": wall_s * 1000,
        }
    finally:
        report_path.unlink(missing_ok=True)


def result_key(payload):
    return tuple(payload.get(key) for key in ("count", "first", "last", "sha256"))


def sample_core(case, original, django, env):
    name, start, end, iterations = case
    scripts = {
        "php": ["php", str(original / "benchmarks/php_core.php"), str(iterations), start, end],
        "javascript": ["node", str(original / "benchmarks/js_core.cjs"), str(iterations), start, end],
        "python": [sys.executable, str(original / "benchmarks/python_core.py"), str(iterations), start, end],
    }
    samples = {runtime: [] for runtime in RUNTIMES}
    reference = None
    for runtime in RUNTIMES:
        warm = timed(scripts[runtime], env, original)
        reference = reference or result_key(warm["payload"])
        if result_key(warm["payload"]) != reference:
            raise AssertionError(f"Different Fibonacci result: {name} / {runtime}")
    rng = random.Random(1729)
    for _ in range(SAMPLES):
        order = list(RUNTIMES)
        rng.shuffle(order)
        for runtime in order:
            observed = timed(scripts[runtime], env, original)
            if result_key(observed["payload"]) != reference:
                raise AssertionError(f"Different Fibonacci result: {name} / {runtime}")
            samples[runtime].append({
                "cpu_ms_per_resolve": observed["payload"]["cpu_ms"] / iterations,
                "peak_rss_kib": observed["rss_kib"],
                "wall_ms_total": observed["wall_ms"],
            })
    return {
        "case": name, "iterations_per_process": iterations,
        "samples_per_runtime": SAMPLES,
        "verification": dict(zip(("count", "first", "last", "sha256"), reference)),
        "runtimes": {runtime: {
            "cpu_ms_per_resolve": median([s["cpu_ms_per_resolve"] for s in samples[runtime]]),
            "peak_rss_kib": median([s["peak_rss_kib"] for s in samples[runtime]]),
            "samples": samples[runtime],
        } for runtime in RUNTIMES},
    }


def sample_cli(case, original, django, env):
    name, start, end, _ = case
    scripts = {
        "php": ["php", str(original / "PHP/fibonacci.php"), start, end],
        "django": [sys.executable, str(django / "manage.py"), "fibonacci", start, end],
    }
    samples = {runtime: [] for runtime in scripts}
    first_payload = None
    for runtime in scripts:
        observed = timed(scripts[runtime], env, original)
        payload = observed["payload"]
        assert set(payload) == {"current_month", "current_year", "custom_range"}
        first_payload = first_payload or payload["custom_range"]
        if payload["custom_range"] != first_payload:
            raise AssertionError(f"CLI output mismatch: {name} / {runtime}")
    rng = random.Random(1729)
    for _ in range(SAMPLES):
        order = list(scripts)
        rng.shuffle(order)
        for runtime in order:
            observed = timed(scripts[runtime], env, original)
            if observed["payload"]["custom_range"] != first_payload:
                raise AssertionError(f"CLI output mismatch: {name} / {runtime}")
            samples[runtime].append({
                "cpu_ms": observed["external_cpu_ms"],
                "peak_rss_kib": observed["rss_kib"],
                "wall_ms": observed["wall_ms"],
            })
    return {
        "case": name, "samples_per_runtime": SAMPLES,
        "runtimes": {runtime: {
            "cpu_ms": median([s["cpu_ms"] for s in samples[runtime]]),
            "peak_rss_kib": median([s["peak_rss_kib"] for s in samples[runtime]]),
            "wall_ms": median([s["wall_ms"] for s in samples[runtime]]),
            "samples": samples[runtime],
        } for runtime in scripts},
    }


def markdown(data):
    lines = [
        "# Fibonacci: CPU and peak memory on one GitHub Actions runner",
        "",
        f"Runner: {data['platform']}; PHP {data['versions']['php']}, "
        f"Node {data['versions']['node']}, Python {data['versions']['python']}.",
        "",
        "## Core range resolver (startup excluded)",
        "",
        "| Workload | Runtime | CPU ms / resolve (median) | Peak RSS MiB (median) |",
        "|---|---|---:|---:|",
    ]
    for entry in data["core"]:
        for runtime in RUNTIMES:
            item = entry["runtimes"][runtime]
            lines.append(
                f"| {entry['case']} | {runtime} | {item['cpu_ms_per_resolve']:.4f} "
                f"| {item['peak_rss_kib'] / 1024:.2f} |"
            )
    lines += [
        "",
        "## Actual CLI (startup, parsing, JSON and exit included)",
        "",
        "| Workload | Runtime | CPU ms (median) | Peak RSS MiB (median) | Wall ms (median) |",
        "|---|---|---:|---:|---:|",
    ]
    for entry in data["cli"]:
        for runtime in ("php", "django"):
            item = entry["runtimes"][runtime]
            lines.append(
                f"| {entry['case']} | {runtime} | {item['cpu_ms']:.2f} "
                f"| {item['peak_rss_kib'] / 1024:.2f} | {item['wall_ms']:.2f} |"
            )
    lines += [
        "",
        "Five fresh process samples after an excluded process warmup. "
        "Core CPU excludes one additional warmup inside each process. "
        "CPU is user + system, and each result was checked against a SHA-256 digest.",
        "Peak RSS includes interpreter and imports; this is not isolated heap allocation.",
        "JavaScript is Node/V8 running the original browser-side algorithm, not a browser DOM.",
        "End-to-end CLI compares only PHP with Django because the original JS has no CLI.",
        "GitHub-hosted VM timings vary, so small differences are not conclusive.",
        f"Original: {data['commits']['original']}; Django: {data['commits']['django']}.",
    ]
    return "\n".join(lines) + "\n"


def git_sha(path):
    cmd = ["git", "-C", str(path), "rev-parse", "HEAD"]
    return subprocess.run(cmd, text=True, capture_output=True, check=True).stdout.strip()


def version(args):
    return subprocess.run(args, text=True, capture_output=True, check=True).stdout.strip().splitlines()[0]


def main():
    parser = argparse.ArgumentParser(description=__doc__)
    parser.add_argument("--original-dir", type=Path, required=True)
    parser.add_argument("--django-dir", type=Path, required=True)
    parser.add_argument("--output-dir", type=Path, default=Path("benchmark-results"))
    args = parser.parse_args()
    original, django = args.original_dir.resolve(), args.django_dir.resolve()
    for target in (original / "JAVASCRIPT/fibonacci.js",
                   original / "PHP/fibonacci.php", django / "manage.py"):
        if not target.is_file():
            raise FileNotFoundError(target)
    for exe in (TIME_BIN, "node", "php", "git"):
        if not shutil.which(exe):
            raise RuntimeError(f"Executable not found: {exe}")
    env = {**os.environ,
           "BENCH_ORIGINAL_ROOT": str(original),
           "BENCH_DJANGO_ROOT": str(django),
           "DJANGO_DEBUG": "1",
           "DJANGO_SECRET_KEY": "benchmark-development-only-key"}
    data = {
        "timestamp_utc": datetime.now(timezone.utc).isoformat(),
        "platform": platform.platform(),
        "versions": {
            "python": platform.python_version(),
            "php": version(["php", "-r", "echo PHP_VERSION;"]),
            "node": version(["node", "--version"]),
        },
        "commits": {"original": git_sha(original), "django": git_sha(django)},
        "core": [], "cli": [],
    }
    print(f"Original {data['commits']['original'][:12]}, "
          f"Django {data['commits']['django'][:12]}", flush=True)
    for case in CASE_DEFS:
        output = sample_core(case, original, django, env)
        data["core"].append(output)
        print("Core " + case[0] + ": " + ", ".join(
            f"{key}={output['runtimes'][key]['cpu_ms_per_resolve']:.4f}ms"
            for key in RUNTIMES), flush=True)
    for case in CLI_CASES:
        output = sample_cli(case, original, django, env)
        data["cli"].append(output)
        print("CLI " + case[0] + ": " + ", ".join(
            f"{key}={output['runtimes'][key]['cpu_ms']:.2f}ms"
            for key in ("php", "django")), flush=True)
    args.output_dir.mkdir(parents=True, exist_ok=True)
    (args.output_dir / "results.json").write_text(
        json.dumps(data, indent=2) + "\n", encoding="utf8")
    report = markdown(data)
    (args.output_dir / "summary.md").write_text(report, encoding="utf8")
    print(report)


if __name__ == "__main__":
    main()
