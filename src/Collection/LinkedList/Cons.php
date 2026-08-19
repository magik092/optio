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
    private readonly int $size;

    /**
     * @param T             $value
     * @param LinkedList<T> $rest
     */
    public function __construct(
        private readonly mixed $value,
        private readonly LinkedList $rest,
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
}
