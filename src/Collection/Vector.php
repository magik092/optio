<?php

declare(strict_types=1);

namespace Optio\Collection;

use Optio\Collection\Internal\Sliding;
use Optio\Collection\Vector\Leaf;
use Optio\Collection\Vector\Node;
use Optio\Collection\Vector\Operations;

/**
 * An immutable, indexed sequence backed by a 32-way branching trie with
 * path-copying, giving O(log32 n) get/update/append — instead of the
 * O(n) recursive traversal munusphp's GenericList relies on. Use Vector
 * when you need indexed access or append; use LinkedList when you need
 * O(1) prepend/head/tail instead.
 *
 * @template-covariant T = never
 *
 * @implements Traversable<T>
 */
final class Vector implements Traversable
{
    /**
     * @param int<0, max> $size
     */
    private function __construct(
        private readonly Node|Leaf|null $root,
        private readonly int $height,
        private readonly int $size,
    ) {
    }

    /**
     * @return self<never>
     */
    public static function empty(): self
    {
        return new self(null, 0, 0);
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
        $vector = self::empty();
        foreach ($elements as $element) {
            $vector = $vector->append($element);
        }

        return $vector;
    }

    /**
     * @return T
     */
    public function get(int $index): mixed
    {
        if ($index < 0 || $index >= $this->size || $this->root === null) {
            throw new \OutOfRangeException(sprintf('Index %d is out of range for a Vector of length %d.', $index, $this->size));
        }

        return Operations::get($this->root, $this->height, $index);
    }

    /**
     * @template U
     *
     * @param U $value
     *
     * @return self<T|U>
     */
    public function update(int $index, mixed $value): self
    {
        if ($index < 0 || $index >= $this->size) {
            throw new \OutOfRangeException(sprintf('Index %d is out of range for a Vector of length %d.', $index, $this->size));
        }

        $newRoot = Operations::write($this->root, $this->height, $index, $value);

        return new self($newRoot, $this->height, $this->size);
    }

    /**
     * @template U
     *
     * @param U $value
     *
     * @return self<T|U>
     */
    public function append(mixed $value): self
    {
        $index = $this->size;
        $height = $this->height;

        if ($this->root !== null && $index === Operations::capacityAt($height)) {
            $newRoot = new Node([$this->root]);
            ++$height;
        } else {
            $newRoot = $this->root;
        }

        $newRoot = Operations::write($newRoot, $height, $index, $value);

        return new self($newRoot, $height, $this->size + 1);
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

    public function count(): int
    {
        return $this->size;
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
        Operations::each($this->root, function (mixed $value) use (&$elements): void {
            $elements[] = $value;
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
     * @param \Closure(T): bool $predicate
     *
     * @return self<T>
     */
    public function filter(\Closure $predicate): self
    {
        return self::ofAll(array_values(array_filter($this->toArray(), $predicate)));
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
        foreach ($this->toArray() as $value) {
            $accumulator = $combine($accumulator, $value);
        }

        return $accumulator;
    }

    /**
     * @param \Closure(T): void $action
     */
    public function forEach(\Closure $action): void
    {
        foreach ($this->toArray() as $value) {
            $action($value);
        }
    }

    /**
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
