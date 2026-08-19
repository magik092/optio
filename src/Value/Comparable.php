<?php

declare(strict_types=1);

namespace Optio\Value;

/**
 * Contract for value objects with custom equality semantics, used by
 * Comparator::equals() instead of PHP's loose `==` comparison.
 */
interface Comparable
{
    public function equals(mixed $other): bool;
}
