<?php

declare(strict_types=1);

namespace Optio\Collection;

use Optio\Collection\Hamt\Leaf;
use Optio\Collection\Hamt\Node;
use Optio\Collection\Hamt\Operations;
use Optio\Collection\Internal\Sliding;
use Optio\Control\Option;
use Optio\Tuple\Tuple2;
use Optio\Value\Comparator;

/**
 * Immutable, persistent key-value map backed by a HAMT (see Optio\Collection\Hamt).
 * Every mutating operation returns a new HashMap; the original is left unchanged.
 * Object keys must implement Hashable for Comparator::hash()/equals() to work correctly.
 *
 * @template K = never
 * @template V = never
 *
 * @implements \IteratorAggregate<int, Tuple2<K, V>>
 */
final class HashMap implements \IteratorAggregate, \Countable
{
    /**
     * @return \Closure(mixed, mixed): bool
     */
    private static function keyEquals(): \Closure
    {
        return static fn (mixed $stored, mixed $key): bool => $stored instanceof Tuple2 && Comparator::equals($stored[0], $key);
    }

    /**
     * @param int<0, max>                  $size
     * @param \Closure(mixed): string|null $hasher
     */
    private function __construct(
        private readonly Node|Leaf|null $root,
        private readonly int $size,
        private readonly ?\Closure $hasher = null,
    ) {
    }

    private function hashOf(mixed $key): int
    {
        return $this->hasher !== null ? crc32(($this->hasher)($key)) : Comparator::hash($key);
    }

    /**
     * @return self<K, V>
     */
    private function emptyLike(): self
    {
        return $this->hasher !== null ? new self(null, 0, $this->hasher) : self::empty();
    }

    /**
     * @return self<never, never>
     */
    public static function empty(): self
    {
        return new self(null, 0);
    }

    /**
     * @template MK
     *
     * @param \Closure(MK): string $hasher
     *
     * @return self<MK, never>
     */
    public static function emptyHashed(\Closure $hasher): self
    {
        return new self(null, 0, $hasher);
    }

    /**
     * @template MK
     * @template MV
     *
     * @param iterable<array{0: MK, 1: MV}>|iterable<Tuple2<MK, MV>> $entries entries to insert, either as
     *                                                                        [$key, $value] pairs or as Tuple2 instances
     *
     * @return self<MK, MV>
     */
    public static function ofAll(iterable $entries): self
    {
        $map = self::empty();
        foreach ($entries as $entry) {
            $map = $map->put(self::entryKey($entry), self::entryValue($entry));
        }

        return $map;
    }

    /**
     * @template EEK
     * @template EEV
     *
     * @param array{0: EEK, 1: EEV}|Tuple2<EEK, EEV> $entry
     *
     * @return EEK
     */
    private static function entryKey(array|Tuple2 $entry): mixed
    {
        return $entry[0];
    }

    /**
     * @template EEK
     * @template EEV
     *
     * @param array{0: EEK, 1: EEV}|Tuple2<EEK, EEV> $entry
     *
     * @return EEV
     */
    private static function entryValue(array|Tuple2 $entry): mixed
    {
        return $entry[1];
    }

    /**
     * @template U
     * @template W
     *
     * @param U $key
     * @param W $value
     *
     * @return self<K|U, V|W>
     */
    public function put(mixed $key, mixed $value): self
    {
        $entry = new Tuple2($key, $value);
        [$newRoot, $isNew] = Operations::put($this->root, $this->hashOf($key), self::keyEquals(), $entry, $key);

        return new self($newRoot, $this->size + ($isNew ? 1 : 0), $this->hasher);
    }

    /**
     * @return self<K, V>
     */
    public function remove(mixed $key): self
    {
        [$newRoot, $removed] = Operations::remove($this->root, $this->hashOf($key), self::keyEquals(), $key);

        return new self($newRoot, max(0, $this->size - ($removed ? 1 : 0)), $this->hasher);
    }

    /**
     * @return Option<V>
     */
    public function get(mixed $key): Option
    {
        return Operations::get($this->root, $this->hashOf($key), self::keyEquals(), $key)
            ->map(fn (mixed $entry): mixed => $this->valueOf($this->requireTuple2($entry)));
    }

    /**
     * @param Tuple2<K, V> $entry
     *
     * @return V
     */
    private function valueOf(Tuple2 $entry): mixed
    {
        return $entry[1];
    }

    public function containsKey(mixed $key): bool
    {
        return $this->get($key)->isDefined();
    }

    /**
     * @return HashSet<K>
     */
    public function keys(): HashSet
    {
        $keys = array_map(fn (Tuple2 $entry) => $this->keyOf($entry), $this->toArray());

        return $this->hasher !== null ? HashSet::ofAllHashed($this->hasher, $keys) : HashSet::ofAll($keys);
    }

    /**
     * @param Tuple2<K, V> $entry
     *
     * @return K
     */
    private function keyOf(Tuple2 $entry): mixed
    {
        return $entry[0];
    }

    /**
     * @return list<V>
     */
    public function values(): array
    {
        return array_map(fn (Tuple2 $entry) => $this->valueOf($entry), $this->toArray());
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
     * @return \Iterator<int, Tuple2<K, V>>
     */
    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->toArray());
    }

    /**
     * @return list<Tuple2<K, V>>
     */
    public function toArray(): array
    {
        $entries = [];
        Operations::each($this->root, function (mixed $entry) use (&$entries): void {
            $entries[] = $this->requireTuple2($entry);
        });

        return $entries;
    }

    /**
     * @param \Closure(Tuple2<K, V>): mixed $mapper
     *
     * @return self<K, V>
     */
    public function map(\Closure $mapper): self
    {
        $result = self::empty();
        foreach ($this->toArray() as $entry) {
            $mapped = $this->requireTuple2($mapper($entry));
            $result = $result->put($this->keyOf($mapped), $this->valueOf($mapped));
        }

        return $result;
    }

    /**
     * @template MK
     * @template MV
     *
     * @param \Closure(Tuple2<K, V>): Tuple2<MK, MV> $mapper
     * @param \Closure(MK): string                   $hasher
     *
     * @return self<MK, MV>
     */
    public function mapHashed(\Closure $mapper, \Closure $hasher): self
    {
        $result = self::emptyHashed($hasher);
        foreach ($this->toArray() as $entry) {
            $mapped = $mapper($entry);
            $result = $result->put(self::entryKey($mapped), self::entryValue($mapped));
        }

        return $result;
    }

    /**
     * @return Tuple2<K, V>
     */
    private function requireTuple2(mixed $mapped): Tuple2
    {
        if (!$mapped instanceof Tuple2) {
            throw new \LogicException('HashMap invariant violated: expected an entry of Tuple2, got '.get_debug_type($mapped).'.');
        }

        return $mapped;
    }

    /**
     * @param \Closure(Tuple2<K, V>): bool $predicate
     *
     * @return self<K, V>
     */
    public function filter(\Closure $predicate): self
    {
        $result = $this->emptyLike();
        foreach ($this->toArray() as $entry) {
            if ($predicate($entry)) {
                $result = $result->put($this->keyOf($entry), $this->valueOf($entry));
            }
        }

        return $result;
    }

    /**
     * Window order follows this collection's hash-based iteration order and
     * is unspecified from the caller's perspective; only completeness is
     * guaranteed — every entry appears in at least one window.
     *
     * @return Vector<self<K, V>>
     */
    public function sliding(int $size, int $step): Vector
    {
        $windows = Sliding::windows($this->toArray(), $size, $step);

        return Vector::ofAll(array_map(fn (array $chunk): self => self::ofAll($chunk), $windows));
    }

    /**
     * @return Vector<self<K, V>>
     */
    public function grouped(int $size): Vector
    {
        return $this->sliding($size, $size);
    }

    /**
     * @template U
     *
     * @param U                            $initial
     * @param \Closure(U, Tuple2<K, V>): U $combine
     *
     * @return U
     */
    public function fold(mixed $initial, \Closure $combine): mixed
    {
        $accumulator = $initial;
        foreach ($this->toArray() as $entry) {
            $accumulator = $combine($accumulator, $entry);
        }

        return $accumulator;
    }

    /**
     * @param \Closure(Tuple2<K, V>): void $action
     */
    public function forEach(\Closure $action): void
    {
        foreach ($this->toArray() as $entry) {
            $action($entry);
        }
    }
}
