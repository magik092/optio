<?php

declare(strict_types=1);

/**
 * Example 7: HashMap and HashSet — combining two collections with merge().
 *
 * merge() is what you reach for once configuration or permissions come from
 * more than one place: a defaults map plus per-request overrides, or two
 * sets of granted roles that both need to apply. HashMap::merge() takes an
 * optional callback to resolve key conflicts — without one, the argument's
 * value simply wins, which is right for overrides but wrong for counters
 * that should accumulate instead of being replaced. HashSet::merge() has no
 * such choice to make: it's a plain union.
 *
 * This example also covers two easy-to-miss details: without a callback, a
 * genuine key conflict is resolved by silently overwriting with the
 * argument's value (not just "no overlap" happy paths); and merge() always
 * hashes using the receiving collection's Hasher, never the argument's.
 *
 * Run: php8.1 examples/07_merge_basics.php
 */

require __DIR__.'/../vendor/autoload.php';

use Optio\Collection\HashMap;
use Optio\Collection\HashSet;

// 1. HashMap::merge() without a callback, happy path: no keys overlap, so
// every entry from both maps survives untouched.
$defaults = HashMap::empty()
    ->put('retries', 3)
    ->put('cache_enabled', true);

$overrides = HashMap::empty()
    ->put('log_level', 'debug');

$config = $defaults->merge($overrides);

printf(
    "No overlap: retries=%d, cache_enabled=%s, log_level=%s\n\n",
    $config->get('retries')->getOrElse(0),
    $config->get('cache_enabled')->getOrElse(false) ? 'true' : 'false',
    $config->get('log_level')->getOrElse('n/a'),
);

// 1b. Now with an actual conflict: 'timeout_seconds' exists in both maps.
// Without a callback, $other (the argument passed to merge()) silently wins
// — $defaults' value of 30 is discarded, not combined in any way.
$defaultsWithTimeout = $defaults->put('timeout_seconds', 30);
$overridesWithTimeout = $overrides->put('timeout_seconds', 90);

$mergedWithOverwrite = $defaultsWithTimeout->merge($overridesWithTimeout);

printf(
    "Conflict on 'timeout_seconds': defaults had %d, overrides had %d, merge() kept %d (the argument wins, silently).\n\n",
    $defaultsWithTimeout->get('timeout_seconds')->getOrElse(0),
    $overridesWithTimeout->get('timeout_seconds')->getOrElse(0),
    $mergedWithOverwrite->get('timeout_seconds')->getOrElse(0),
);

// 2. HashMap::merge() with a callback: it only runs for keys present in both
// maps, so unique keys from either side are inserted untouched. Here it's
// used to add up request counts reported by two different servers instead of
// letting the second server's number silently overwrite the first.
$requestsFromServerA = HashMap::empty()
    ->put('/login', 120)
    ->put('/checkout', 45);

$requestsFromServerB = HashMap::empty()
    ->put('/login', 80)
    ->put('/catalog', 200);

$totalRequests = $requestsFromServerA->merge(
    $requestsFromServerB,
    fn (int $left, int $right): int => $left + $right,
);

echo "Combined request counts:\n";
foreach ($totalRequests->toArray() as $entry) {
    printf("  %s: %d\n", $entry[0], $entry[1]);
}
echo "\n";

// 3. HashSet::merge() is a plain union — no conflicts to resolve, duplicates
// just collapse. Useful for combining roles granted through different paths
// (e.g. a base role plus a team-specific grant).
$baseRoles = HashSet::of('viewer', 'editor');
$teamRoles = HashSet::of('editor', 'approver');

$grantedRoles = $baseRoles->merge($teamRoles);

printf("Granted roles (%d, no duplicates): %s\n\n", $grantedRoles->length(), implode(', ', $grantedRoles->toArray()));

// 4. merge() with a Hasher: StockItem is a plain DTO with no Hashable
// implementation, so both sides need emptyHashed()/ofHashed() with the same
// identity function (SKU + warehouse) to be usable at all.
final class StockItem
{
    public function __construct(
        public readonly string $sku,
        public readonly string $warehouse,
        public readonly int $quantity,
    ) {
    }
}

$byLocation = fn (StockItem $item): string => $item->sku.'@'.$item->warehouse;

$stockAtEast = HashSet::ofHashed($byLocation, new StockItem('SKU-100', 'WH-EAST', 12));
$stockAtWest = HashSet::ofHashed($byLocation, new StockItem('SKU-100', 'WH-WEST', 4), new StockItem('SKU-200', 'WH-WEST', 9));

$allStock = $stockAtEast->merge($stockAtWest);
echo "Merged StockItem sets via a shared Hasher:\n";
foreach ($allStock->toArray() as $item) {
    printf("  %s @ %s (qty %d)\n", $item->sku, $item->warehouse, $item->quantity);
}
echo "\n";

// 5. merge() always hashes with the RECEIVER's Hasher — the argument's
// Hasher, if different, is ignored entirely for the merge itself. $left
// groups by SKU + warehouse, $right groups by SKU alone; the two Hashers
// disagree on how to bucket things. That doesn't matter for correctness,
// because a Hasher only decides bucket placement — equality between two
// candidates in the same bucket still falls back to full value comparison
// (as noted in example 05). $right happens to contain a value-identical
// duplicate of one of $left's items; merge() still recognizes it as the
// same element and doesn't grow the set, even though it was produced under
// $right's differing Hasher.
$bySku = fn (StockItem $item): string => $item->sku;

$left = HashSet::ofHashed($byLocation, new StockItem('SKU-100', 'WH-EAST', 12), new StockItem('SKU-200', 'WH-WEST', 9));
$right = HashSet::ofHashed($bySku, new StockItem('SKU-100', 'WH-EAST', 12), new StockItem('SKU-300', 'WH-NORTH', 1));

$mergedByLeftHasher = $left->merge($right);
printf(
    "left has %d entries, right has %d entries (one value-identical duplicate), merged (using left's Hasher) has %d — the duplicate did not double up.\n",
    $left->length(),
    $right->length(),
    $mergedByLeftHasher->length(),
);
foreach ($mergedByLeftHasher->toArray() as $item) {
    printf("  %s @ %s (qty %d)\n", $item->sku, $item->warehouse, $item->quantity);
}
