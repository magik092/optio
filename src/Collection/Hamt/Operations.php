<?php

declare(strict_types=1);

namespace Optio\Collection\Hamt;

use Optio\Control\Option;

/**
 * Immutable operations on a Hash Array Mapped Trie (HAMT): get/put/remove/each/count.
 * Every mutation returns a new root, sharing untouched subtrees with the old one.
 *
 * @internal not part of Optio's public API — a shared implementation detail
 * of HashMap and HashSet
 */
final class Operations
{
    private const BITS = 5;
    private const MASK = 0x1F;

    /**
     * @param \Closure(mixed, mixed): bool $keyEquals compares a stored entry against a lookup key
     *
     * @return Option<mixed>
     */
    public static function get(Node|Leaf|null $root, int $hash, \Closure $keyEquals, mixed $key, int $shift = 0): Option
    {
        if ($root === null) {
            return Option::none();
        }

        if ($root instanceof Leaf) {
            if ($root->hash !== $hash) {
                return Option::none();
            }

            foreach ($root->entries as $entry) {
                if ($keyEquals($entry, $key)) {
                    return Option::some($entry);
                }
            }

            return Option::none();
        }

        $idx = ($hash >> $shift) & self::MASK;
        $bit = 1 << $idx;
        if (($root->bitmap & $bit) === 0) {
            return Option::none();
        }

        $pos = self::popcount($root->bitmap & ($bit - 1));

        return self::get($root->children[$pos], $hash, $keyEquals, $key, $shift + self::BITS);
    }

    /**
     * @param \Closure(mixed, mixed): bool $keyEquals compares a stored entry against a lookup key
     *
     * @return array{0: Node|Leaf, 1: bool}
     */
    public static function put(Node|Leaf|null $root, int $hash, \Closure $keyEquals, mixed $entry, mixed $key, int $shift = 0): array
    {
        if ($root === null) {
            return [new Leaf($hash, [$entry]), true];
        }

        if ($root instanceof Leaf) {
            if ($root->hash === $hash) {
                foreach ($root->entries as $i => $existing) {
                    if ($keyEquals($existing, $key)) {
                        $entries = $root->entries;
                        $entries[$i] = $entry;

                        return [new Leaf($hash, array_values($entries)), false];
                    }
                }

                return [new Leaf($hash, [...$root->entries, $entry]), true];
            }

            return [self::mergeLeafWithNewEntry($root, $hash, $entry, $shift), true];
        }

        $idx = ($hash >> $shift) & self::MASK;
        $bit = 1 << $idx;

        if (($root->bitmap & $bit) === 0) {
            $pos = self::popcount($root->bitmap & ($bit - 1));
            $children = $root->children;
            array_splice($children, $pos, 0, [new Leaf($hash, [$entry])]);

            return [new Node($root->bitmap | $bit, $children), true];
        }

        $pos = self::popcount($root->bitmap & ($bit - 1));
        [$newChild, $isNew] = self::put($root->children[$pos], $hash, $keyEquals, $entry, $key, $shift + self::BITS);
        $children = $root->children;
        $children[$pos] = $newChild;

        return [new Node($root->bitmap, $children), $isNew];
    }

    /**
     * @param \Closure(mixed, mixed): bool $keyEquals compares a stored entry against a lookup key
     *
     * @return array{0: Node|Leaf|null, 1: bool}
     *
     * @internal the resulting trie is not canonicalized after removal — a Node with a single
     * remaining Leaf child does not collapse into a bare Leaf. This has no effect on get/each/count,
     * but matters if structural equals()/hashCode() are ever added to HashMap/HashSet: such comparisons
     * must go through each()/get(), never through comparing the trie shape directly.
     */
    public static function remove(Node|Leaf|null $root, int $hash, \Closure $keyEquals, mixed $key, int $shift = 0): array
    {
        if ($root === null) {
            return [null, false];
        }

        if ($root instanceof Leaf) {
            if ($root->hash !== $hash) {
                return [$root, false];
            }

            $entries = array_values(array_filter(
                $root->entries,
                fn (mixed $entry): bool => !$keyEquals($entry, $key),
            ));

            if (count($entries) === count($root->entries)) {
                return [$root, false];
            }

            return [$entries === [] ? null : new Leaf($hash, $entries), true];
        }

        $idx = ($hash >> $shift) & self::MASK;
        $bit = 1 << $idx;
        if (($root->bitmap & $bit) === 0) {
            return [$root, false];
        }

        $pos = self::popcount($root->bitmap & ($bit - 1));
        [$newChild, $removed] = self::remove($root->children[$pos], $hash, $keyEquals, $key, $shift + self::BITS);

        if (!$removed) {
            return [$root, false];
        }

        if ($newChild === null) {
            $children = $root->children;
            array_splice($children, $pos, 1);
            $newBitmap = $root->bitmap & ~$bit;

            return [$newBitmap === 0 ? null : new Node($newBitmap, $children), true];
        }

        $children = $root->children;
        $children[$pos] = $newChild;

        return [new Node($root->bitmap, $children), true];
    }

    /**
     * @param \Closure(mixed): void $callback
     */
    public static function each(Node|Leaf|null $root, \Closure $callback): void
    {
        if ($root === null) {
            return;
        }

        if ($root instanceof Leaf) {
            foreach ($root->entries as $entry) {
                $callback($entry);
            }

            return;
        }

        foreach ($root->children as $child) {
            self::each($child, $callback);
        }
    }

    /**
     * @return int<0, max>
     */
    public static function count(Node|Leaf|null $root): int
    {
        $count = 0;
        self::each($root, function () use (&$count): void {
            ++$count;
        });

        return $count;
    }

    /**
     * Resolves a hash collision at the trie level by splitting into Node(s) until
     * the existing and new leaf land in different slots (or, on a true hash
     * collision, are merged deeper into a single Leaf sharing that hash).
     */
    private static function mergeLeafWithNewEntry(Leaf $existingLeaf, int $newHash, mixed $newEntry, int $shift): Node
    {
        $existingIdx = ($existingLeaf->hash >> $shift) & self::MASK;
        $newIdx = ($newHash >> $shift) & self::MASK;

        if ($existingIdx === $newIdx) {
            $child = self::mergeLeafWithNewEntry($existingLeaf, $newHash, $newEntry, $shift + self::BITS);
            $bit = 1 << $existingIdx;

            return new Node($bit, [$child]);
        }

        $newLeaf = new Leaf($newHash, [$newEntry]);
        $bit1 = 1 << $existingIdx;
        $bit2 = 1 << $newIdx;
        $children = $existingIdx < $newIdx ? [$existingLeaf, $newLeaf] : [$newLeaf, $existingLeaf];

        return new Node($bit1 | $bit2, $children);
    }

    /**
     * Counts set bits, used to translate a bitmap slot into its compacted array position.
     */
    private static function popcount(int $x): int
    {
        $count = 0;
        while ($x !== 0) {
            $x &= $x - 1;
            ++$count;
        }

        return $count;
    }
}
