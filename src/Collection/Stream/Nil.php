<?php

declare(strict_types=1);

namespace Optio\Collection\Stream;

use Optio\Collection\Stream;
use Optio\Exception\NoSuchElementException;

/**
 * The empty Stream.
 *
 * @extends Stream<never>
 */
final class Nil extends Stream
{
    public function isEmpty(): bool
    {
        return true;
    }

    public function head(): mixed
    {
        throw new NoSuchElementException('head() called on an empty Stream');
    }

    public function tail(): Stream
    {
        throw new NoSuchElementException('tail() called on an empty Stream');
    }
}
