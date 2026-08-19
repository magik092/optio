<?php

declare(strict_types=1);

/**
 * Example 5: using a Hasher closure instead of implementing Hashable.
 *
 * HashSet and HashMap need to know how to hash their elements/keys. The
 * default path is implementing Optio\Value\Hashable on the object. But
 * sometimes the type is a plain DTO you don't own, or don't want to burden
 * with an interface just to put it in a set. For that case, ofHashed(),
 * emptyHashed() and mapHashed() let you pass a plain closure describing
 * identity instead.
 *
 * Run: php8.1 examples/05_hasher_without_hashable.php
 */

require __DIR__.'/../vendor/autoload.php';

use Optio\Collection\HashMap;
use Optio\Collection\HashSet;
use Optio\Exception\HashableContractException;

final class StockItem
{
    public function __construct(
        public readonly string $sku,
        public readonly string $warehouse,
        public readonly int $quantity,
    ) {
    }
}

$batch = [
    new StockItem('SKU-100', 'WH-EAST', 12),
    new StockItem('SKU-100', 'WH-EAST', 12),
    new StockItem('SKU-100', 'WH-WEST', 4),
    new StockItem('SKU-200', 'WH-EAST', 30),
];

echo "StockItem is a plain DTO, no Hashable implemented.\n\n";

try {
    HashSet::of(...$batch);
} catch (HashableContractException $e) {
    printf("HashSet::of() refuses it: %s\n\n", $e->getMessage());
}

// A hasher is just a closure describing what makes two items "the same" —
// here, the combination of SKU and warehouse (quantity is not part of identity).
$byLocation = fn (StockItem $item): string => $item->sku.'@'.$item->warehouse;

$stock = HashSet::ofHashed($byLocation, ...$batch);
printf("HashSet::ofHashed() deduplicated %d items down to %d.\n\n", count($batch), $stock->length());

// filter() carries the hasher forward, so a duplicate added afterwards is
// still deduplicated against what filter() kept.
$eastOnly = $stock->filter(fn (StockItem $item): bool => str_starts_with($item->warehouse, 'WH-EAST'));
$eastOnly = $eastOnly->add(new StockItem('SKU-200', 'WH-EAST', 999));

echo "filter() kept the WH-EAST items and the hasher along with it:\n";
foreach ($eastOnly->toArray() as $item) {
    printf("  %s @ %s (qty %d)\n", $item->sku, $item->warehouse, $item->quantity);
}
printf("still %d entries after adding a location duplicate.\n\n", $eastOnly->length());

// HashMap can use the same DTO as a key via emptyHashed(). The hasher only
// decides which bucket a key lands in — equality between two candidates in
// the same bucket still falls back to full object comparison unless the DTO
// implements Comparable, so the quantity field has to match too, not just
// sku/warehouse. Keeping it at 0 here for every key sidesteps that.
$reorderPoints = HashMap::emptyHashed($byLocation)
    ->put(new StockItem('SKU-100', 'WH-EAST', 0), 20)
    ->put(new StockItem('SKU-100', 'WH-WEST', 0), 5)
    ->put(new StockItem('SKU-200', 'WH-EAST', 0), 15);

$lookup = new StockItem('SKU-100', 'WH-EAST', 0);
printf(
    "reorder point for %s@%s: %s\n\n",
    $lookup->sku,
    $lookup->warehouse,
    $reorderPoints->get($lookup)->getOrElse(0),
);

final class Warehouse
{
    public function __construct(public readonly string $name)
    {
    }
}

// map() cannot know whether the mapped key type is safe to hash by default,
// so it resets to plain Hashable-based hashing. Warehouse doesn't implement
// Hashable, so collapsing our StockItem keys down to just their warehouse
// with map() fails as soon as a Warehouse key needs to be hashed.
try {
    $reorderPoints->map(fn ($entry) => Optio\Tuple::of(new Warehouse($entry[0]->warehouse), $entry[1]));
} catch (HashableContractException $e) {
    printf("map() to Warehouse keys fails: %s\n\n", $e->getMessage());
}

// mapHashed() supplies the new hasher in the same step, and also lets
// entries that now share a key (both were WH-EAST) merge via put().
$perWarehouse = $reorderPoints->mapHashed(
    fn ($entry) => Optio\Tuple::of(new Warehouse($entry[0]->warehouse), $entry[1]),
    fn (Warehouse $w): string => $w->name,
);

echo "mapHashed() re-attached a hasher for the Warehouse keys and merged duplicates:\n";
foreach ($perWarehouse->toArray() as $entry) {
    printf("  %s -> reorder at %d\n", $entry[0]->name, $entry[1]);
}
