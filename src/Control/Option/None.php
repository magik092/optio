<?php

declare(strict_types=1);

namespace Optio\Control\Option;

use Optio\Control\Either;
use Optio\Control\Either\Left;
use Optio\Control\Option;
use Optio\Control\TryTo;
use Optio\Control\TryTo\Failure;
use Optio\Exception\NoSuchElementException;

/**
 * Option variant representing absence of a value.
 *
 * @extends Option<never>
 */
final class None extends Option
{
    public function isDefined(): bool
    {
        return false;
    }

    public function map(\Closure $mapper): Option
    {
        return $this;
    }

    public function flatMap(\Closure $mapper): Option
    {
        return $this;
    }

    public function filter(\Closure $predicate): Option
    {
        return $this;
    }

    public function getOrElse(mixed $default): mixed
    {
        return $default;
    }

    public function fold(\Closure $onNone, \Closure $onSome): mixed
    {
        return $onNone();
    }

    /**
     * @template U
     *
     * @param U $ifNone
     *
     * @return Left<U>
     */
    public function toEither(mixed $ifNone): Either
    {
        return new Left($ifNone);
    }

    /**
     * @return Failure
     */
    public function toTryTo(): TryTo
    {
        return new Failure(new NoSuchElementException('Option::toTryTo() called on None'));
    }
}
