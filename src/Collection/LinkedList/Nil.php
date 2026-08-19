<?php

declare(strict_types=1);

namespace Optio\Collection\LinkedList;

use Optio\Collection\LinkedList;
use Optio\Exception\NoSuchElementException;

/**
 * The empty LinkedList.
 *
 * @extends LinkedList<never>
 */
final class Nil extends LinkedList
{
    public function isEmpty(): bool
    {
        return true;
    }

    public function head(): mixed
    {
        throw new NoSuchElementException('head() called on an empty LinkedList');
    }

    public function tail(): LinkedList
    {
        throw new NoSuchElementException('tail() called on an empty LinkedList');
    }

    public function length(): int
    {
        return 0;
    }

    public function prepend(mixed $value): LinkedList
    {
        return new Cons($value, $this);
    }
}
