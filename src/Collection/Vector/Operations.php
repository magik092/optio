<?php

declare(strict_types=1);

namespace Optio\Collection\Vector;

/**
 * @internal not part of Optio's public API — a shared implementation
 * detail of Vector
 */
final class Operations
{
    private const BITS = 5;
    private const MASK = 0x1F;
    private const BRANCHING = 32;

    public static function capacityAt(int $height): int
    {
        return self::BRANCHING ** ($height + 1);
    }

    public static function get(Node|Leaf $root, int $height, int $index): mixed
    {
        if ($root instanceof Leaf) {
            return $root->values[$index & self::MASK];
        }

        $slot = ($index >> ($height * self::BITS)) & self::MASK;

        return self::get($root->children[$slot], $height - 1, $index);
    }

    public static function write(Node|Leaf|null $root, int $height, int $index, mixed $value): Node|Leaf
    {
        if ($height === 0) {
            $values = $root instanceof Leaf ? $root->values : [];
            $values[$index & self::MASK] = $value;

            return new Leaf($values);
        }

        $children = $root instanceof Node ? $root->children : [];
        $slot = ($index >> ($height * self::BITS)) & self::MASK;
        $children[$slot] = self::write($children[$slot] ?? null, $height - 1, $index, $value);

        return new Node($children);
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
            foreach ($root->values as $value) {
                $callback($value);
            }

            return;
        }

        foreach ($root->children as $child) {
            self::each($child, $callback);
        }
    }
}
