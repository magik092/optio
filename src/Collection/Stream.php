<?php

declare(strict_types=1);

namespace Optio\Collection;

use Optio\Collection\Internal\Sliding;
use Optio\Collection\Stream\Cons;
use Optio\Collection\Stream\Nil;

/**
 * A lazy, potentially infinite sequence — a Cons/Nil chain like LinkedList,
 * but Cons's tail is a Lazy<Stream<T>> instead of an eagerly-built
 * LinkedList, so elements past the head are only computed on demand.
 * Methods that must consume the WHOLE stream (toArray, fold, forEach,
 * length, sliding, grouped) will never return on a truly infinite stream —
 * call take($n) first. map/filter/take are lazy: each call builds exactly
 * one node and defers the rest, so they terminate immediately even on an
 * infinite source. getIterator() is also lazy — iterating with `foreach`
 * and `break`ing early never forces more than what was consumed.
 *
 * @template-covariant T = never
 *
 * @implements Traversable<T>
 */
abstract class Stream implements Traversable
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
        $iterator = (function () use ($elements) {
            yield from $elements;
        })();

        return self::fromIterator($iterator);
    }

    /**
     * @template U
     *
     * @param \Iterator<mixed, U> $iterator
     *
     * @return self<U>
     */
    private static function fromIterator(\Iterator $iterator): self
    {
        if (!$iterator->valid()) {
            return self::empty();
        }

        $head = $iterator->current();

        return new Cons($head, function () use ($iterator): self {
            $iterator->next();

            return self::fromIterator($iterator);
        });
    }

    /**
     * @return self<int>
     */
    public static function from(int $start): self
    {
        return new Cons($start, fn () => self::from($start + 1));
    }

    /**
     * @template U
     *
     * @param U              $seed
     * @param \Closure(U): U $iterator
     *
     * @return self<U>
     */
    public static function iterate(mixed $seed, \Closure $iterator): self
    {
        return new Cons($seed, fn () => self::iterate($iterator($seed), $iterator));
    }

    /**
     * Unlike every other factory on this class, the supplier is invoked once
     * eagerly at construction time (before any consumption of the returned
     * stream). This is a deliberate, documented exception to the class's
     * laziness contract: Cons's head is a plain value, not itself a
     * Lazy<T> — only its tail is deferred — so producing the head requires
     * calling the supplier immediately. Routing the first call through an
     * infinite generator and Stream's private fromIterator() helper does not
     * avoid this: fromIterator() calls $iterator->valid() right away, and
     * PHP forces a generator to run up to its first `yield` on the first
     * valid()/current() call, so the supplier still fires before
     * continually() returns. Making the head itself lazy would require
     * changing Cons's constructor signature (and every other call site that
     * passes an already-known concrete head value), which is a larger
     * architectural change than this fix warrants.
     *
     * @template U
     *
     * @param \Closure(): U $supplier
     *
     * @return self<U>
     */
    public static function continually(\Closure $supplier): self
    {
        return new Cons($supplier(), fn () => self::continually($supplier));
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
     * @return self<T>
     */
    public function take(int $n): self
    {
        if ($n <= 0 || $this->isEmpty()) {
            return self::empty();
        }

        if ($n === 1) {
            return new Cons($this->head(), fn (): self => self::empty());
        }

        return new Cons($this->head(), fn () => $this->tail()->take($n - 1));
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
        if ($this->isEmpty()) {
            return self::empty();
        }

        return new Cons($mapper($this->head()), fn () => $this->tail()->map($mapper));
    }

    /**
     * @param \Closure(T): bool $predicate
     *
     * @return self<T>
     */
    public function filter(\Closure $predicate): self
    {
        $node = $this;
        while (!$node->isEmpty() && !$predicate($node->head())) {
            $node = $node->tail();
        }

        if ($node->isEmpty()) {
            return self::empty();
        }

        return new Cons($node->head(), fn () => $node->tail()->filter($predicate));
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
        $stream = $this;
        while (!$stream->isEmpty()) {
            $accumulator = $combine($accumulator, $stream->head());
            $stream = $stream->tail();
        }

        return $accumulator;
    }

    /**
     * @param \Closure(T): void $action
     */
    public function forEach(\Closure $action): void
    {
        $stream = $this;
        while (!$stream->isEmpty()) {
            $action($stream->head());
            $stream = $stream->tail();
        }
    }

    /**
     * @return list<T>
     */
    public function toArray(): array
    {
        $elements = [];
        $stream = $this;
        while (!$stream->isEmpty()) {
            $elements[] = $stream->head();
            $stream = $stream->tail();
        }

        return $elements;
    }

    /**
     * @return int<0, max>
     */
    public function length(): int
    {
        $count = 0;
        $stream = $this;
        while (!$stream->isEmpty()) {
            ++$count;
            $stream = $stream->tail();
        }

        return $count;
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
        $stream = $this;
        while (!$stream->isEmpty()) {
            yield $stream->head();
            $stream = $stream->tail();
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
