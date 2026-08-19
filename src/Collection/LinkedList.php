<?php

declare(strict_types=1);

namespace Optio\Collection;

use Optio\Collection\Internal\Sliding;
use Optio\Collection\LinkedList\Cons;
use Optio\Collection\LinkedList\Nil;

/**
 * An immutable, singly-linked list (a Cons/Nil chain). O(1) prepend/head/
 * tail, but O(n) random access — use Vector instead when you need indexed
 * access or fast append. Every Traversable method here (toArray, map,
 * filter, fold, forEach) walks the chain with a plain loop, never
 * recursion, so there is no stack-depth risk while traversing regardless
 * of list length — unlike munusphp's GenericList, which this class
 * replaces. (A very deep, unbroken chain built via repeated prepend()
 * can still hit PHP's own engine-level recursive object-teardown limit
 * when finally freed — this is a PHP runtime limitation shared by any
 * Cons-cell structure, not something this library's code can avoid;
 * prefer Vector for such extreme sizes.).
 *
 * @template-covariant T = never
 *
 * @implements Traversable<T>
 */
abstract class LinkedList implements Traversable
{
    /**
     * @return self<never>
     */
    public static function empty(): self
    {
        return new Nil();
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
        $reversed = self::empty();
        foreach ($elements as $element) {
            $reversed = $reversed->prepend($element);
        }

        return $reversed->reverse();
    }

    abstract public function isEmpty(): bool;

    /**
     * @return T
     */
    abstract public function head(): mixed;

    /**
     * @return self<T>
     */
    abstract public function tail(): self;

    /**
     * @return int<0, max>
     */
    abstract public function length(): int;

    /**
     * @template U
     *
     * @param U $value
     *
     * @return self<T|U>
     */
    abstract public function prepend(mixed $value): self;

    /**
     * @return self<T>
     */
    public function reverse(): self
    {
        $result = self::empty();
        $node = $this;
        while (!$node->isEmpty()) {
            $result = $result->prepend($node->head());
            $node = $node->tail();
        }

        return $result;
    }

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
        $node = $this;
        while (!$node->isEmpty()) {
            $elements[] = $node->head();
            $node = $node->tail();
        }

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
        $node = $this;
        while (!$node->isEmpty()) {
            $accumulator = $combine($accumulator, $node->head());
            $node = $node->tail();
        }

        return $accumulator;
    }

    /**
     * @param \Closure(T): void $action
     */
    public function forEach(\Closure $action): void
    {
        $node = $this;
        while (!$node->isEmpty()) {
            $action($node->head());
            $node = $node->tail();
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
