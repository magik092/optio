<?php

declare(strict_types=1);

namespace Optio\Collection\Stream;

use Optio\Collection\Stream;
use Optio\Control\Lazy;

/**
 * A non-empty Stream cell: a value plus a lazily-computed tail.
 *
 * @template-covariant T
 *
 * @extends Stream<T>
 */
final class Cons extends Stream
{
    /**
     * Freeing a cell used to free its rest from inside the engine's own
     * destructor, one nested call per cell, which overflowed the C stack on
     * long chains. Instead, each cell hands its rest to this queue and
     * detaches it; the outermost destructor then drains the queue in a
     * loop, so cells are freed one at a time at constant stack depth.
     * Shared tails are safe: the queue only holds a reference, so a tail
     * still used by another list survives being popped.
     *
     * @var list<Lazy<Stream<mixed>>>
     */
    private static array $pending = [];

    private static bool $draining = false;

    /**
     * @var Lazy<Stream<T>>
     */
    private Lazy $tail;

    /**
     * @param T                     $head
     * @param \Closure(): Stream<T> $tail
     */
    public function __construct(
        private readonly mixed $head,
        \Closure $tail,
    ) {
        $this->tail = Lazy::of($tail);
    }

    public function isEmpty(): bool
    {
        return false;
    }

    public function head(): mixed
    {
        return $this->head;
    }

    public function tail(): Stream
    {
        return $this->tail->get();
    }

    public function __destruct()
    {
        self::$pending[] = $this->tail;
        unset($this->tail);
        if (self::$draining) {
            return;
        }

        self::$draining = true;
        while (self::$pending !== []) {
            array_pop(self::$pending);
        }
        self::$draining = false;
    }
}
