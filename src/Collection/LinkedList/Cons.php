<?php

declare(strict_types=1);

namespace Optio\Collection\LinkedList;

use Optio\Collection\LinkedList;

/**
 * A non-empty LinkedList cell: one value plus the rest of the list.
 *
 * @template-covariant T
 *
 * @extends LinkedList<T>
 */
final class Cons extends LinkedList
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
     * @var list<LinkedList<mixed>>
     */
    private static array $pending = [];

    private static bool $draining = false;

    private readonly int $size;

    /**
     * @param T             $value
     * @param LinkedList<T> $rest
     */
    public function __construct(
        private readonly mixed $value,
        private LinkedList $rest,
    ) {
        $this->size = 1 + $rest->length();
    }

    public function isEmpty(): bool
    {
        return false;
    }

    public function head(): mixed
    {
        return $this->value;
    }

    public function tail(): LinkedList
    {
        return $this->rest;
    }

    public function length(): int
    {
        return $this->size;
    }

    public function prepend(mixed $value): LinkedList
    {
        return new self($value, $this);
    }

    public function __destruct()
    {
        self::$pending[] = $this->rest;
        unset($this->rest);
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
