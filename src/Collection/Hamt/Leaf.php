<?php

declare(strict_types=1);

namespace Optio\Collection\Hamt;

/**
 * Holds every entry that shares the exact same integer hash. Normally a
 * single entry; more than one only when two distinct keys produce the same
 * hash (a genuine hash collision), resolved by linear scan with the
 * caller-supplied equality closure.
 *
 * @internal not part of Optio's public API — a shared implementation detail
 * of HashMap and HashSet
 */
final class Leaf
{
    /**
     * @param list<mixed> $entries
     */
    public function __construct(
        public readonly int $hash,
        public readonly array $entries,
    ) {
    }
}
