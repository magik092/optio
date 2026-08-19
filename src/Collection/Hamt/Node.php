<?php

declare(strict_types=1);

namespace Optio\Collection\Hamt;

/**
 * A bitmap-indexed branch node in the trie. `$bitmap` has one bit set per
 * occupied slot (0-31); `$children` is a compacted array (no gaps) holding
 * only the present children, ordered by slot index.
 *
 * @internal not part of Optio's public API — a shared implementation detail
 * of HashMap and HashSet
 */
final class Node
{
    /**
     * @param array<int, Node|Leaf> $children
     */
    public function __construct(
        public readonly int $bitmap,
        public readonly array $children,
    ) {
    }
}
