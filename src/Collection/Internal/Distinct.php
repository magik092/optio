<?php

declare(strict_types=1);

namespace Optio\Collection\Internal;

use Optio\Collection\HashSet;

/**
 * The shared deduplication algorithm behind every sequence's distinct()/
 * distinctBy() — a pure function over a plain array, so it is written and
 * tested once instead of once per collection class. Keys are compared the
 * way HashSet compares elements (Comparator::equals()/hash()).
 *
 * @internal not part of Optio's public API
 */
final class Distinct
{
    /**
     * Elements whose key $keyExtractor($element) occurs for the first time, in their original order.
     *
     * @template T
     * @template K
     *
     * @param list<T>        $elements
     * @param \Closure(T): K $keyExtractor
     *
     * @return list<T>
     */
    public static function by(array $elements, \Closure $keyExtractor): array
    {
        $seen = HashSet::empty();
        $kept = [];
        foreach ($elements as $element) {
            $key = $keyExtractor($element);
            if (!$seen->contains($key)) {
                $seen = $seen->add($key);
                $kept[] = $element;
            }
        }

        return $kept;
    }
}
