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
     * @var Lazy<Stream<T>>
     */
    private readonly Lazy $tail;

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
}
