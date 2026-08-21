<?php

declare(strict_types=1);

namespace Optio\Collection;

use Optio\Tuple\Tuple2;

/**
 * Immutable, persistent set of unique elements that iterates in insertion
 * order — unlike HashSet, whose iteration order follows its HAMT's
 * hash-bit layout. Implemented as a thin wrapper around
 * LinkedHashMap<T, bool>, exactly as HashSet-backed-by-HashMap is a
 * common pattern in other languages' standard libraries.
 *
 * @template-covariant T = never
 *
 * @implements Traversable<T>
 */
final class LinkedHashSet implements Traversable
{
    /**
     * @param LinkedHashMap<T, bool>   $map
     * @param \Closure(T): string|null $hasher
     */
    private function __construct(
        private readonly LinkedHashMap $map,
        private readonly ?\Closure $hasher = null,
    ) {
    }

    /**
     * @return self<never>
     */
    public static function empty(): self
    {
        return new self(LinkedHashMap::empty());
    }

    /**
     * @template U
     *
     * @param \Closure(U): string $hasher
     *
     * @return self<U>
     */
    public static function emptyHashed(\Closure $hasher): self
    {
        return new self(LinkedHashMap::emptyHashed($hasher), $hasher);
    }

    /**
     * @template U
     *
     * @param U ...$elements
     *
     * @return self<U>
     */
    public static function of(mixed ...$elements): self
    {
        return self::ofAll($elements);
    }

    /**
     * @template U
     *
     * @param iterable<U> $elements
     *
     * @return self<U>
     */
    public static function ofAll(iterable $elements): self
    {
        $map = LinkedHashMap::empty();
        foreach ($elements as $element) {
            $map = $map->put($element, true);
        }

        return new self($map);
    }

    /**
     * @template U
     *
     * @param \Closure(U): string $hasher
     * @param U                   ...$elements
     *
     * @return self<U>
     */
    public static function ofHashed(\Closure $hasher, mixed ...$elements): self
    {
        return self::ofAllHashed($hasher, $elements);
    }

    /**
     * @template U
     *
     * @param \Closure(U): string $hasher
     * @param iterable<U>         $elements
     *
     * @return self<U>
     */
    public static function ofAllHashed(\Closure $hasher, iterable $elements): self
    {
        $set = self::emptyHashed($hasher);
        foreach ($elements as $element) {
            $set = $set->add($element);
        }

        return $set;
    }

    /**
     * @template U
     *
     * @param U $element
     *
     * @return self<T|U>
     */
    public function add(mixed $element): self
    {
        return new self($this->map->put($element, true), $this->widenedHasher());
    }

    /**
     * Widens the stored hasher's parameter type to `mixed` so it can be
     * forwarded into a `self<T|U>` (a wider element type than the hasher's
     * own `T`) without violating closure-parameter contravariance. Purely
     * a static-typing device — the underlying closure is unchanged.
     *
     * @return \Closure(mixed): string|null
     */
    private function widenedHasher(): ?\Closure
    {
        $hasher = $this->hasher;

        return $hasher !== null ? static fn (mixed $element): string => $hasher($element) : null;
    }

    /**
     * @return self<T>
     */
    public function remove(mixed $element): self
    {
        return new self($this->map->remove($element), $this->hasher);
    }

    /**
     * @template U
     *
     * @param self<U> $other
     *
     * @return self<T|U>
     */
    public function merge(self $other): self
    {
        $result = $this;
        foreach ($other->toArray() as $element) {
            $result = $result->add($element);
        }

        return $result;
    }

    public function contains(mixed $element): bool
    {
        return $this->map->containsKey($element);
    }

    /**
     * @return int<0, max>
     */
    public function length(): int
    {
        return $this->map->length();
    }

    public function isEmpty(): bool
    {
        return $this->map->isEmpty();
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return $this->length();
    }

    /**
     * @return list<T>
     */
    public function toArray(): array
    {
        return array_map(fn (Tuple2 $entry): mixed => $entry[0], $this->map->toArray());
    }

    /**
     * @return \Iterator<int, T>
     */
    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->toArray());
    }

    /**
     * @template U
     *
     * @param \Closure(T): U $mapper
     *
     * @return self<U>
     */
    public function map(\Closure $mapper): self
    {
        return self::ofAll(array_map($mapper, $this->toArray()));
    }

    /**
     * @template U
     *
     * @param \Closure(T): U      $mapper
     * @param \Closure(U): string $hasher
     *
     * @return self<U>
     */
    public function mapHashed(\Closure $mapper, \Closure $hasher): self
    {
        return self::ofAllHashed($hasher, array_map($mapper, $this->toArray()));
    }

    /**
     * @param \Closure(T): bool $predicate
     *
     * @return self<T>
     */
    public function filter(\Closure $predicate): self
    {
        $filtered = array_values(array_filter($this->toArray(), $predicate));

        return $this->hasher !== null ? self::ofAllHashed($this->hasher, $filtered) : self::ofAll($filtered);
    }

    /**
     * @template U
     *
     * @param U                 $initial
     * @param \Closure(U, T): U $combine
     *
     * @return U
     */
    public function fold(mixed $initial, \Closure $combine): mixed
    {
        $accumulator = $initial;
        foreach ($this->toArray() as $element) {
            $accumulator = $combine($accumulator, $element);
        }

        return $accumulator;
    }

    /**
     * @param \Closure(T): void $action
     */
    public function forEach(\Closure $action): void
    {
        foreach ($this->toArray() as $element) {
            $action($element);
        }
    }

    /**
     * Window order is insertion order (unlike HashSet, where it is
     * unspecified) — this is the defining property of this class.
     *
     * @return Vector<self<T>>
     */
    public function sliding(int $size, int $step): Vector
    {
        $windows = Internal\Sliding::windows($this->toArray(), $size, $step);

        $result = [];
        foreach ($windows as $chunk) {
            $result[] = $this->hasher !== null ? self::ofAllHashed($this->hasher, $chunk) : self::ofAll($chunk);
        }

        return Vector::ofAll($result);
    }

    /**
     * @return Vector<self<T>>
     */
    public function grouped(int $size): Vector
    {
        return $this->sliding($size, $size);
    }
}
