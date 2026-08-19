<?php

declare(strict_types=1);

namespace Optio\Collection\Vector;

/**
 * A leaf node holding up to 32 raw elements, densely packed left-to-right.
 *
 * @internal not part of Optio's public API — a shared implementation
 * detail of Vector
 */
final class Leaf
{
    /**
     * @param array<int, mixed> $values
     */
    public function __construct(
        public readonly array $values,
    ) {
    }
}
