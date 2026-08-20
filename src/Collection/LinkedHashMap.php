<?php

declare(strict_types=1);

namespace Optio\Collection;

use Optio\Collection\Internal\Sliding;
use Optio\Collection\LinkedHashMap\Slot;
use Optio\Control\Option;
use Optio\Tuple\Tuple2;

/**
 * Immutable, persistent key-value map that iterates in insertion order —
 * unlike HashMap, whose iteration order follows its HAMT's hash-bit
 * layout. Backed by a Vector<K> holding the insertion sequence plus an
 * internal HashMap<K, Slot<K,V>> for O(log32 n) lookup.
 *
 * remove() never touches the Vector directly (deleting from the middle of
 * a persistent trie is O(n)) — it only removes the key from the internal
 * HashMap. A Vector position is "live" only if the key stored there still
 * maps, in the internal HashMap, to a Slot whose recorded index equals
 * that exact position; anything else (key absent, or present but now
 * pointing at a different, later position from a later re-insertion of
 * the same key) is dead and skipped during iteration. This tells stale
 * positions apart from live ones without ever storing a sentinel
 * "tombstone" value inside the Vector<K> itself. Dead positions
 * accumulate as removes happen; once they outnumber live entries, a full
 * reindex compacts both structures back down to only-live entries.
 *
 * @template K = never
 * @template V = never
 *
 * @implements \IteratorAggregate<int, Tuple2<K, V>>
 */
final class LinkedHashMap implements \IteratorAggregate, \Countable
{
    /**
     * @param Vector<K>                     $list   insertion order
     * @param HashMap<K, Slot<mixed,mixed>> $map
     * @param int                           $offset how many positions have been trimmed from the
     *                                              front of $list by a prior reindex
     */
    private function __construct(
        private readonly Vector $list,
        private readonly HashMap $map,
        private readonly int $offset,
    ) {
    }

    /**
     * @return self<never, never>
     */
    public static function empty(): self
    {
        return new self(Vector::empty(), HashMap::empty(), 0);
    }

    /**
     * @template MK
     * @template MV
     *
     * @param iterable<array{0: MK, 1: MV}>|iterable<Tuple2<MK, MV>> $entries
     *
     * @return self<MK, MV>
     */
    public static function ofAll(iterable $entries): self
    {
        $result = self::empty();
        foreach ($entries as $entry) {
            $result = $result->put(self::entryKey($entry), self::entryValue($entry));
        }

        return $result;
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
        return $this->map->get($key)->fold(
            function () use ($key, $value): self {
                $index = $this->offset + $this->list->length();
                $newList = $this->list->append($key);
                $newMap = $this->map->put($key, new Slot(new Tuple2($key, $value), $index));

                return new self($newList, $newMap, $this->offset);
            },
            /**
             * @param Slot<K, V> $existingSlot
             */
            function (Slot $existingSlot) use ($key, $value): self {
                $newMap = $this->map->put($key, new Slot(new Tuple2($key, $value), $existingSlot->index));

                return new self($this->list, $newMap, $this->offset);
            },
        );
    }

    /**
     * @return Option<V>
     */
    public function get(mixed $key): Option
    {
        return $this->map->get($key)->map($this->slotValue(...));
    }

    /**
     * @param Slot<mixed, mixed> $slot
     *
     * @return V
     */
    private function slotValue(Slot $slot): mixed
    {
        return $slot->entry[1];
    }

    public function containsKey(mixed $key): bool
    {
        return $this->map->containsKey($key);
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
     * @return self<K, V>
     */
    public function remove(mixed $key): self
    {
        if (!$this->map->containsKey($key)) {
            return $this;
        }

        $next = new self($this->list, $this->map->remove($key), $this->offset);

        return $next->compactIfNeeded();
    }

    /**
     * @return self<K, V>
     */
    private function compactIfNeeded(): self
    {
        $deadCount = $this->list->length() - $this->map->length();

        if ($deadCount <= $this->map->length()) {
            return $this;
        }

        $newList = Vector::empty();
        $newMap = HashMap::empty();
        $index = 0;

        foreach ($this->liveEntries() as [$key, $slot]) {
            $newList = $newList->append($key);
            $newMap = $newMap->put($key, new Slot($slot->entry, $index));
            ++$index;
        }

        return new self($newList, $newMap, 0);
    }

    /**
     * Walks $list in order, keeping only positions whose key still maps
     * (in $map) to a Slot recorded at that exact position — i.e. the live
     * ones, in insertion order.
     *
     * @return list<array{0: K, 1: Slot<mixed, mixed>}>
     */
    private function liveEntries(): array
    {
        $result = [];

        for ($i = 0; $i < $this->list->length(); ++$i) {
            $key = $this->list->get($i);
            $slotOption = $this->map->get($key);

            if (!$slotOption->isDefined()) {
                continue;
            }

            $slot = $slotOption->fold(
                function (): never {
                    throw new \LogicException('unreachable: isDefined() was just checked true');
                },
                fn (Slot $slot): Slot => $slot,
            );

            if ($slot->index === $this->offset + $i) {
                $result[] = [$key, $slot];
            }
        }

        return $result;
    }

    /**
     * @return list<Tuple2<K, V>>
     */
    public function toArray(): array
    {
        return array_map(
            fn (array $pair): Tuple2 => new Tuple2($pair[0], $this->slotValue($pair[1])),
            $this->liveEntries(),
        );
    }

    /**
     * @return \Iterator<int, Tuple2<K, V>>
     */
    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->toArray());
    }

    /**
     * @return int<0, max>
     */
    public function count(): int
    {
        return $this->length();
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
     * @param Tuple2<K, V> $entry
     *
     * @return K
     */
    private function keyOf(Tuple2 $entry): mixed
    {
        return $entry[0];
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

    /**
     * @return Tuple2<K, V>
     */
    private function requireTuple2(mixed $mapped): Tuple2
    {
        if (!$mapped instanceof Tuple2) {
            throw new \LogicException('LinkedHashMap invariant violated: expected an entry of Tuple2, got '.get_debug_type($mapped).'.');
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
        $result = self::empty();
        foreach ($this->toArray() as $entry) {
            if ($predicate($entry)) {
                $result = $result->put($entry[0], $entry[1]);
            }
        }

        return $result;
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

    /**
     * Window order is insertion order (unlike HashMap, where it is
     * unspecified) — this is the defining property of this class.
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
     * @return LinkedHashSet<K>
     */
    public function keys(): LinkedHashSet
    {
        return LinkedHashSet::ofAll(array_map(fn (Tuple2 $entry) => $this->keyOf($entry), $this->toArray()));
    }

    /**
     * @return list<V>
     */
    public function values(): array
    {
        return array_map(fn (Tuple2 $entry) => $this->valueOf($entry), $this->toArray());
    }
}
