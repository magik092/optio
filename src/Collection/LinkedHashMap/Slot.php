<?php

declare(strict_types=1);

namespace Optio\Collection\LinkedHashMap;

use Optio\Tuple\Tuple2;

/**
 * An entry plus the position it occupies in a LinkedHashMap's internal
 * insertion-order Vector, so a stale Vector position (left behind by a
 * remove() that never touches the Vector) can be told apart from a live
 * one by comparing indices, with no sentinel value involved.
 *
 * @template K
 * @template V
 */
final class Slot
{
    /**
     * @param Tuple2<K, V> $entry
     */
    public function __construct(
        public readonly Tuple2 $entry,
        public readonly int $index,
    ) {
    }
}
