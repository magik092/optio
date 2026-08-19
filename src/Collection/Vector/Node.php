<?php

declare(strict_types=1);

namespace Optio\Collection\Vector;

/**
 * A branch node in the position-indexed trie backing Vector. Unlike
 * Hamt\Node, there is no bitmap: children are always densely packed
 * left-to-right (indices 0..31), because Vector positions are always
 * contiguous integers 0..size-1, never sparse like hash codes.
 *
 * @internal not part of Optio's public API — a shared implementation
 * detail of Vector
 */
final class Node
{
    /**
     * @param array<int, Node|Leaf> $children
     */
    public function __construct(
        public readonly array $children,
    ) {
    }
}
