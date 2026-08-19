<?php

declare(strict_types=1);

/**
 * Example 4: HAMT (Optio\Collection\HashMap) vs linear-scan array (Munus\Collection\Map).
 *
 * The whole point of Optio's HashMap is that it is a real Hash Array Mapped
 * Trie: put()/get()/remove() are O(log32 n), effectively O(1) in practice.
 *
 * munusphp/munus's Map is a wrapper around a plain PHP array of Tuple2
 * pairs, scanned linearly (`findPosition()`) on every put()/get()/
 * containsKey(). That makes a single put() O(n), and building a map of n
 * elements by n sequential put() calls O(n^2) overall.
 *
 * This script measures both, and — because O(n^2) with n=100 000 would mean
 * ~10 billion comparisons and could run for a very long time — it does NOT
 * blindly run munusphp at the full 100k. Instead it:
 *
 *   1. Measures munusphp's build time at small, doubling sizes (500, 1000,
 *      2000, ...), stopping once the cumulative real time spent measuring
 *      would exceed a ~2 minute budget (self-adjusting per machine).
 *   2. Fits the quadratic curve (time ~ c * n^2) to that data.
 *   3. Extrapolates mathematically what 100 000 elements would cost,
 *      clearly labelled as an EXTRAPOLATION, not a measurement.
 *
 * Optio's HashMap, by contrast, is actually built and measured at the full
 * 100 000 elements, because it is fast enough to do so directly.
 *
 * Run: php8.1 examples/04_benchmark_vs_munus.php
 */

require __DIR__.'/../vendor/autoload.php';
require __DIR__.'/../../munus/vendor/autoload.php';

use Optio\Collection\HashMap;
use Munus\Collection\Map as MunusMap;

/** Small helper: current wall-clock time in seconds, high resolution. */
function now(): float
{
    return hrtime(true) / 1_000_000_000;
}

/** Format seconds as a human string, choosing sensible units. */
function fmtDuration(float $seconds): string
{
    if ($seconds < 1.0) {
        return sprintf('%.1f ms', $seconds * 1000);
    }
    if ($seconds < 60.0) {
        return sprintf('%.2f s', $seconds);
    }
    if ($seconds < 3600.0) {
        return sprintf('%.1f min', $seconds / 60);
    }

    return sprintf('%.1f h', $seconds / 3600);
}

// ---------------------------------------------------------------------
// Part 1: Optio\Collection\HashMap — build 100 000 entries, then time
// 10 000 random get() lookups on the fully built map.
// ---------------------------------------------------------------------

const N = 100_000;
const LOOKUPS = 10_000;

echo "=== Optio\\Collection\\HashMap (HAMT) ===\n";

$t0 = now();
$optioMap = HashMap::empty();
for ($i = 0; $i < N; $i++) {
    $optioMap = $optioMap->put("key-{$i}", $i);
}
$optioBuildTime = now() - $t0;

echo sprintf("Build %d entries via sequential put(): %s\n", N, fmtDuration($optioBuildTime));

// 10 000 random reads on the fully-built map of size N.
mt_srand(42);
$randomIndexes = [];
for ($i = 0; $i < LOOKUPS; $i++) {
    $randomIndexes[] = mt_rand(0, N - 1);
}

$t0 = now();
foreach ($randomIndexes as $idx) {
    $optioMap->get("key-{$idx}");
}
$optioLookupTimeAtFullSize = now() - $t0;

echo sprintf(
    "%d random get() lookups at n=%d: %s (%.3f microseconds/lookup)\n\n",
    LOOKUPS,
    N,
    fmtDuration($optioLookupTimeAtFullSize),
    ($optioLookupTimeAtFullSize / LOOKUPS) * 1_000_000
);

// Also measure Optio's get() cost at a small size (n=1000) to demonstrate
// that lookup time is essentially independent of map size (O(log32 n)).
$smallOptioMap = HashMap::empty();
for ($i = 0; $i < 1000; $i++) {
    $smallOptioMap = $smallOptioMap->put("key-{$i}", $i);
}
$smallIndexes = [];
mt_srand(42);
for ($i = 0; $i < LOOKUPS; $i++) {
    $smallIndexes[] = mt_rand(0, 999);
}
$t0 = now();
foreach ($smallIndexes as $idx) {
    $smallOptioMap->get("key-{$idx}");
}
$optioLookupTimeAtSmallSize = now() - $t0;

echo "=== Optio HashMap: get() cost is ~independent of map size ===\n";
echo sprintf(
    "%d random get() at n=1 000:   %s (%.3f microseconds/lookup)\n",
    LOOKUPS,
    fmtDuration($optioLookupTimeAtSmallSize),
    ($optioLookupTimeAtSmallSize / LOOKUPS) * 1_000_000
);
echo sprintf(
    "%d random get() at n=100 000: %s (%.3f microseconds/lookup)\n",
    LOOKUPS,
    fmtDuration($optioLookupTimeAtFullSize),
    ($optioLookupTimeAtFullSize / LOOKUPS) * 1_000_000
);
$ratio = $optioLookupTimeAtFullSize / max($optioLookupTimeAtSmallSize, 1e-9);
echo sprintf(
    "Size increased 100x (1 000 -> 100 000), lookup time changed by only %.2fx -> consistent with O(log32 n) ~= O(1).\n\n",
    $ratio
);

// ---------------------------------------------------------------------
// Part 2: Munus\Collection\Map — measure at small sizes, extrapolate the
// O(n^2) build curve to n=100 000.
// ---------------------------------------------------------------------

echo "=== Munus\\Collection\\Map (linear-scan array) ===\n";
echo "Measuring build time at small, doubling sizes to characterize the growth curve...\n\n";

// Rather than hard-coding a fixed list of sample sizes (which either
// wastes a fast machine's budget or overruns a slow one), we keep doubling
// n and measuring for real as long as the CUMULATIVE wall-clock time spent
// in this section stays under a ~2 minute budget. This makes the script
// self-adjusting across machines: fast hardware gets more, larger, more
// convincing real data points; slow hardware still finishes promptly.
const MUNUS_TIME_BUDGET_SECONDS = 110.0; // leave ~10s margin under 2 minutes

$sizes = [];
$munusBuildTimes = [];
$munusLookupTimes = [];
$cumulativeMunusTime = 0.0;
$size = 500;

while (true) {
    $t0 = now();
    $munusMap = MunusMap::empty();
    for ($i = 0; $i < $size; $i++) {
        $munusMap = $munusMap->put("key-{$i}", $i);
    }
    $buildTime = now() - $t0;

    // Lookup cost at this size: LOOKUPS random get()s (or fewer for tiny
    // sizes, capped at $size, to keep it meaningful).
    $numLookups = min(LOOKUPS, $size);
    mt_srand(42);
    $idxs = [];
    for ($i = 0; $i < $numLookups; $i++) {
        $idxs[] = mt_rand(0, $size - 1);
    }
    $t0 = now();
    foreach ($idxs as $idx) {
        $munusMap->get("key-{$idx}");
    }
    $lookupTime = now() - $t0;

    $cumulativeMunusTime += $buildTime + $lookupTime;

    $sizes[] = $size;
    $munusBuildTimes[$size] = $buildTime;
    $munusLookupTimes[$size] = [$numLookups, $lookupTime];

    echo sprintf(
        "n=%-6d build: %-10s | %d get(): %-10s (%.3f microseconds/lookup)   [cumulative: %s]\n",
        $size,
        fmtDuration($buildTime),
        $numLookups,
        fmtDuration($lookupTime),
        ($lookupTime / $numLookups) * 1_000_000,
        fmtDuration($cumulativeMunusTime)
    );

    // Before committing to the next (4x costlier, since O(n^2)) size,
    // estimate its cost from the current point's O(n^2) scaling and only
    // attempt it if it plausibly fits inside the remaining budget.
    $nextSize = $size * 2;
    $projectedNextCost = ($buildTime + $lookupTime) * 4.0; // doubling n -> ~4x under O(n^2)
    if ($cumulativeMunusTime + $projectedNextCost > MUNUS_TIME_BUDGET_SECONDS || $nextSize > $size * 64) {
        break;
    }
    $size = $nextSize;
}

echo sprintf("\n(Stopped measuring further sizes: the next doubling would likely exceed the ~%s real-time budget for this section.)\n\n", fmtDuration(MUNUS_TIME_BUDGET_SECONDS));

// Fit time = c * n^2 using the largest measured sample (least relative
// noise), then cross-check with the growth ratio between successive sizes.
$largestSize = end($sizes);
$largestTime = $munusBuildTimes[$largestSize];
$c = $largestTime / ($largestSize ** 2);
$extrapolated100k = $c * (N ** 2);

echo "\n--- Quadratic growth check (doubling n should ~4x the build time) ---\n";
$prevSize = null;
foreach ($sizes as $size) {
    if ($prevSize !== null) {
        $ratio = $munusBuildTimes[$size] / max($munusBuildTimes[$prevSize], 1e-9);
        echo sprintf(
            "n: %d -> %d (2x)   time ratio: %.2fx  (expected ~4x for O(n^2))\n",
            $prevSize,
            $size,
            $ratio
        );
    }
    $prevSize = $size;
}

echo "\n--- EXTRAPOLATION (NOT measured): projecting munusphp build time to n=100 000 ---\n";
echo sprintf(
    "Fit: time = c * n^2, using largest sample n=%d, time=%s => c = %.6e s/n^2\n",
    $largestSize,
    fmtDuration($largestTime),
    $c
);
echo sprintf(
    "Extrapolated build time for n=%d: %s (EXTRAPOLATED, based on the quadratic fit above, not run to completion)\n\n",
    N,
    fmtDuration($extrapolated100k)
);

// ---------------------------------------------------------------------
// Part 3: Summary comparison table.
// ---------------------------------------------------------------------

echo "=== Summary: build time to reach n=100 000 entries ===\n";
printf("%-15s %-12s %-20s %s\n", 'Library', 'n', 'Build time', 'Note');
printf(
    "%-15s %-12s %-20s %s\n",
    'Optio HashMap',
    number_format(N),
    fmtDuration($optioBuildTime),
    'MEASURED (real run, HAMT, O(n log32 n) total)'
);
printf(
    "%-15s %-12s %-20s %s\n",
    'munusphp Map',
    number_format(N),
    fmtDuration($extrapolated100k),
    sprintf('EXTRAPOLATED from n=%d..%d fit (O(n^2) total, linear-scan per put)', reset($sizes), end($sizes))
);

echo "\n=== Summary: 10 000 random get() lookups on the fully-built map ===\n";
printf("%-15s %-12s %-20s %s\n", 'Library', 'n', 'Lookup time', 'Note');
printf(
    "%-15s %-12s %-20s %s\n",
    'Optio HashMap',
    number_format(N),
    fmtDuration($optioLookupTimeAtFullSize),
    'MEASURED (O(log32 n) per get, effectively O(1))'
);
$largestSample = max($sizes);
[$measuredLookupsAtLargestSample, $lookupTimeAtLargestSample] = $munusLookupTimes[$largestSample];
$munusLookupPerOpAtLargestSample = $lookupTimeAtLargestSample / $measuredLookupsAtLargestSample;
$munusExtrapolatedLookupAt100k = $munusLookupPerOpAtLargestSample * (N / $largestSample) * LOOKUPS;
printf(
    "%-15s %-12s %-20s %s\n",
    'munusphp Map',
    number_format(N),
    fmtDuration($munusExtrapolatedLookupAt100k),
    sprintf('EXTRAPOLATED linearly from n=%s (%d get()s, O(n) per get)', number_format($largestSample), $measuredLookupsAtLargestSample)
);

echo "\n=== Conclusion ===\n";
printf(
    "At n=%s elements, Optio\\Collection\\HashMap built the map in %s (measured),\n",
    number_format(N),
    fmtDuration($optioBuildTime)
);
printf(
    "while Munus\\Collection\\Map would take an estimated %s to build the same map\n",
    fmtDuration($extrapolated100k)
);
printf(
    "(extrapolated from its measured O(n^2) growth curve at n=%d..%d) — roughly %sx slower.\n",
    reset($sizes),
    end($sizes),
    number_format($extrapolated100k / max($optioBuildTime, 1e-9), 0)
);
printf(
    "Random get() lookups show the same story: Optio stays at ~%.3f microseconds/lookup\n",
    ($optioLookupTimeAtFullSize / LOOKUPS) * 1_000_000
);
printf(
    "regardless of map size (log32 n), while munusphp's linear scan means lookup cost\n"
);
printf(
    "grows linearly with n — at n=100 000 it would take an estimated %s for %s lookups,\n",
    fmtDuration($munusExtrapolatedLookupAt100k),
    number_format(LOOKUPS)
);
echo "versus Optio's measured " . fmtDuration($optioLookupTimeAtFullSize) . ".\n";
