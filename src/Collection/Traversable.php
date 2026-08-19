<?php

declare(strict_types=1);

namespace Optio\Collection;

/**
 * Common contract for immutable, iterable collections in this library
 * (map/filter/fold/forEach plus basic size queries).
 *
 * @template-covariant T
 *
 * @extends \IteratorAggregate<int, T>
 */
interface Traversable extends \IteratorAggregate, \Countable
{
    /**
     * @template U
     *
     * @param \Closure(T): U $mapper
     *
     * @return self<U>
     */
    public function map(\Closure $mapper): self;

    /**
     * @param \Closure(T): bool $predicate
     *
     * @return self<T>
     */
    public function filter(\Closure $predicate): self;

    /**
     * @template U
     *
     * @param U                 $initial
     * @param \Closure(U, T): U $combine
     *
     * @return U
     */
    public function fold(mixed $initial, \Closure $combine): mixed;

    /**
     * @param \Closure(T): void $action
     */
    public function forEach(\Closure $action): void;

    /**
     * @return list<T>
     */
    public function toArray(): array;

    public function length(): int;

    public function isEmpty(): bool;

    /**
     * Splits this collection into (possibly overlapping, if `$step < $size`)
     * windows of up to `$size` consecutive elements, moving forward by
     * `$step` each time. The last window may be shorter than `$size` if
     * there are not enough remaining elements. Throws
     * \InvalidArgumentException if `$size` or `$step` is not positive.
     * Ordered implementations (e.g. Vector, LinkedList) preserve element
     * order across windows; hash-based implementations (e.g. HashMap,
     * HashSet) only guarantee completeness — window order is unspecified.
     *
     * @return Vector<self<T>>
     */
    public function sliding(int $size, int $step): Vector;

    /**
     * Splits this collection into non-overlapping chunks of up to `$size`
     * elements each — equivalent to `sliding($size, $size)`. Same ordering
     * guarantees as `sliding()` apply.
     *
     * @return Vector<self<T>>
     */
    public function grouped(int $size): Vector;
}
