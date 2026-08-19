<?php

declare(strict_types=1);

namespace Optio\Value;

/**
 * Contract for objects used as HashMap keys or HashSet elements.
 *
 * hashCode() must be consistent with equals(): if two Comparable
 * instances are equal, their hashCode() must be identical.
 *
 * A class that implements Hashable without also implementing Comparable
 * may still have instances considered equal by Comparator::equals()'s
 * fallback (PHP's `==` operator for objects), yet have different
 * hashCode() values. Such instances will land in different HAMT buckets
 * inside HashMap/HashSet and be treated as distinct entries, effectively
 * duplicating what should be "the same" key/element. To be usable as a
 * HashMap key or HashSet element with correct semantics, implement both
 * Hashable and Comparable consistently.
 */
interface Hashable
{
    public function hashCode(): string;
}
