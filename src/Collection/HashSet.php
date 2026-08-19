<?php

declare(strict_types=1);

namespace Optio\Collection;

use Optio\Collection\Hamt\Leaf;
use Optio\Collection\Hamt\Node;
use Optio\Collection\Hamt\Operations;
use Optio\Collection\Internal\Sliding;
use Optio\Value\Comparator;

/**
 * Immutable, persistent set of unique elements backed by a HAMT (see Optio\Collection\Hamt).
 * Every mutating operation returns a new HashSet; the original is left unchanged.
 * Object elements must implement Hashable for Comparator::hash()/equals() to work correctly.
 *
 * @template-covariant T = never
 *
 * @implements Traversable<T>
 */
final class HashSet implements Traversable
{
    /**
     * @return \Closure(mixed, mixed): bool
     */
    private static function keyEquals(): \Closure
    {
        return static fn (mixed $stored, mixed $key): bool => Comparator::equals($stored, $key);
    }

    /**
     * @param int<0, max>              $size
     * @param \Closure(T): string|null $hasher
     */
    private function __construct(
        private readonly Node|Leaf|null $root,
        private readonly int $size,
        private readonly ?\Closure $hasher = null,
    ) {
    }

    private function hashOf(mixed $element): int
    {
        return $this->hasher !== null ? crc32(($this->hasher)($element)) : Comparator::hash($element);
    }

    /**
     * @return self<never>
     */
    public static function empty(): self
    {
        return new self(null, 0);
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
        return new self(null, 0, $hasher);
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
        $set = self::empty();
        foreach ($elements as $element) {
            $set = $set->add($element);
        }

        return $set;
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
        [$newRoot, $isNew] = Operations::put($this->root, $this->hashOf($element), self::keyEquals(), $element, $element);

        return new self($newRoot, $this->size + ($isNew ? 1 : 0), $this->hasher);
    }

    /**
     * @return self<T>
     */
    public function remove(mixed $element): self
    {
        [$newRoot, $removed] = Operations::remove($this->root, $this->hashOf($element), self::keyEquals(), $element);

        return new self($newRoot, max(0, $this->size - ($removed ? 1 : 0)), $this->hasher);
    }

    public function contains(mixed $element): bool
    {
        return Operations::get($this->root, $this->hashOf($element), self::keyEquals(), $element)->isDefined();
    }

    /**
     * @return int<0, max>
     */
    public function length(): int
    {
        return $this->size;
    }

    public function isEmpty(): bool
    {
        return $this->size === 0;
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return $this->length();
    }

    /**
     * @return \Iterator<int, T>
     */
    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->toArray());
    }

    /**
     * @return list<T>
     */
    public function toArray(): array
    {
        $elements = [];
        Operations::each($this->root, function (mixed $element) use (&$elements): void {
            $elements[] = $element;
        });

        return $elements;
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
     * Window order follows this collection's hash-based iteration order and
     * is unspecified from the caller's perspective; only completeness is
     * guaranteed — every element appears in at least one window.
     *
     * @return Vector<self<T>>
     */
    public function sliding(int $size, int $step): Vector
    {
        $windows = Sliding::windows($this->toArray(), $size, $step);

        return Vector::ofAll(array_map(fn (array $chunk): self => self::ofAll($chunk), $windows));
    }

    /**
     * @return Vector<self<T>>
     */
    public function grouped(int $size): Vector
    {
        return $this->sliding($size, $size);
    }
}
