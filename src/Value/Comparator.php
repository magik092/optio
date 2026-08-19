<?php

declare(strict_types=1);

namespace Optio\Value;

use Optio\Exception\HashableContractException;

/**
 * Central equality/hashing utility used throughout Optio (HashMap, HashSet, Tuple).
 * Prefers Comparable::equals() and Hashable::hashCode() when available, falling
 * back to native comparison for scalars and PHP's `==` for plain objects.
 */
final class Comparator
{
    /**
     * Compares by Comparable::equals() if either side implements it, otherwise
     * falls back to `==` for objects or `===` for scalars.
     */
    public static function equals(mixed $a, mixed $b): bool
    {
        if ($a instanceof Comparable) {
            return $a->equals($b);
        }

        if ($b instanceof Comparable) {
            return $b->equals($a);
        }

        if (is_object($a) && is_object($b)) {
            // Intentional: fallback to PHP value comparison for objects without Comparable interface.
            return $a == $b;
        }

        return $a === $b;
    }

    /**
     * Computes an int hash for use as a HAMT slot key. Objects must implement
     * Hashable (throws HashableContractException otherwise).
     */
    public static function hash(mixed $value): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value)) {
            return crc32($value);
        }

        if (is_bool($value)) {
            return $value ? 1 : 0;
        }

        if (is_float($value)) {
            return crc32((string) $value);
        }

        if ($value instanceof Hashable) {
            return crc32(get_class($value).':'.$value->hashCode());
        }

        if (is_object($value)) {
            throw new HashableContractException(sprintf('Objects used as HashMap/HashSet keys must implement %s, %s does not.', Hashable::class, get_class($value)));
        }

        throw new HashableContractException(sprintf('Cannot hash value of type %s for use as a HashMap/HashSet key.', get_debug_type($value)));
    }
}
