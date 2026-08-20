<?php

declare(strict_types=1);

namespace Optio\Collection;

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
 */
final class LinkedHashMap
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
}
