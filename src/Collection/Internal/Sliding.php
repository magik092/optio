<?php

declare(strict_types=1);

namespace Optio\Collection\Internal;

/**
 * The shared windowing algorithm behind every collection's sliding()/
 * grouped() — a pure function over a plain array, with no dependency on
 * any collection type, so it is written and tested exactly once instead
 * of once per collection class.
 *
 * @internal not part of Optio's public API
 */
final class Sliding
{
    /**
     * @template T
     *
     * @param list<T> $elements
     *
     * @return list<list<T>>
     */
    public static function windows(array $elements, int $size, int $step): array
    {
        if ($size <= 0 || $step <= 0) {
            throw new \InvalidArgumentException(sprintf('size (%d) and step (%d) must both be positive', $size, $step));
        }

        $length = count($elements);
        $windows = [];
        for ($i = 0; $i < $length; $i += $step) {
            $windows[] = array_slice($elements, $i, $size);
        }

        return $windows;
    }
}
