<?php

declare(strict_types=1);

/**
 * Example 8: LinkedHashMap and LinkedHashSet — order-preserving collections.
 *
 * HashMap/HashSet iterate in whatever order their HAMT happens to lay
 * entries out in — fine for lookups, useless when order itself carries
 * meaning. LinkedHashMap/LinkedHashSet keep the same O(log32 n) put/get and
 * the same Hasher/merge() API, but remember insertion order on top.
 *
 * The scenario here is a "recently viewed products" tracker capped at a
 * fixed size: once a product falls off the front, viewing it again should
 * re-add it at the end (most recent) rather than leave it forgotten. Note
 * that re-inserting a key/element that's still *present* does not reorder
 * it — position only changes for a key that was actually removed first and
 * then re-added, which is the behavior this example demonstrates.
 *
 * Run: php8.1 examples/08_linked_hash_collections.php
 */

require __DIR__.'/../vendor/autoload.php';

use Optio\Collection\LinkedHashMap;
use Optio\Collection\LinkedHashSet;

// 1. A visit log as a LinkedHashSet: viewing a product appends its SKU.
// toArray() comes back in the order products were first (or most recently)
// viewed, not in whatever order a hash table would bucket them.
$recentlyViewed = LinkedHashSet::of('SKU-shoes', 'SKU-lamp', 'SKU-mug');

printf("Initial view order: %s\n", implode(' -> ', $recentlyViewed->toArray()));

// 2. The tracker only keeps the 3 most recent products: the shoes are the
// oldest entry, so they get evicted, then the shopper looks at them again.
// Removing and re-adding a key/element lands it at the END, not back at its
// old position — the same rule java.util.LinkedHashMap and Vavr's
// LinkedHashMap follow. (A plain add()/put() on a key that's still present
// would NOT reorder it — only an actual remove()+re-add does.)
$recentlyViewed = $recentlyViewed->remove('SKU-shoes')->add('SKU-shoes');

printf(
    "After evicting and re-viewing the shoes: %s (still %d entries, shoes moved to the end)\n\n",
    implode(' -> ', $recentlyViewed->toArray()),
    $recentlyViewed->length(),
);

// 3. A parallel log for a second browser tab, merged into the first. merge()
// appends the argument's entries after the receiver's, skipping anything
// already present — so the shared SKU-mug view doesn't get duplicated or
// reordered, but the two SKUs unique to the second tab land at the end in
// the order they were viewed there.
$otherTabViews = LinkedHashSet::of('SKU-mug', 'SKU-desk', 'SKU-chair');
$combinedViews = $recentlyViewed->merge($otherTabViews);

printf("Merged across tabs: %s\n\n", implode(' -> ', $combinedViews->toArray()));

// 4. The same eviction-and-return pattern on LinkedHashMap, tracking view
// counts per SKU. put() on a key that's still present only updates its
// value in place — the shoes stay in the middle even though their count
// changes. Only remove() followed by put() relocates a key to the end.
$viewCounts = LinkedHashMap::empty()
    ->put('SKU-shoes', 1)
    ->put('SKU-lamp', 2)
    ->put('SKU-mug', 1);

$viewCounts = $viewCounts->put('SKU-shoes', $viewCounts->get('SKU-shoes')->getOrElse(0) + 1);
echo "In-place update keeps position:\n";
foreach ($viewCounts->toArray() as $entry) {
    printf("  %s: %d view(s)\n", $entry[0], $entry[1]);
}

$shoesCount = $viewCounts->get('SKU-shoes')->getOrElse(0);
$viewCounts = $viewCounts->remove('SKU-shoes')->put('SKU-shoes', $shoesCount + 1);

echo "\nAfter evicting and re-viewing the shoes, they move to the end:\n";
foreach ($viewCounts->toArray() as $entry) {
    printf("  %s: %d view(s)\n", $entry[0], $entry[1]);
}
