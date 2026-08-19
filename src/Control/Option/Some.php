<?php

declare(strict_types=1);

namespace Optio\Control\Option;

use Optio\Control\Either;
use Optio\Control\Either\Right;
use Optio\Control\Option;
use Optio\Control\TryTo;
use Optio\Control\TryTo\Success;

/**
 * Option variant holding a present value.
 *
 * @template-covariant T
 *
 * @extends Option<T>
 */
final class Some extends Option
{
    /**
     * @param T $value
     */
    public function __construct(private readonly mixed $value)
    {
    }

    public function isDefined(): bool
    {
        return true;
    }

    public function map(\Closure $mapper): Option
    {
        return new self($mapper($this->value));
    }

    public function flatMap(\Closure $mapper): Option
    {
        return $mapper($this->value);
    }

    public function filter(\Closure $predicate): Option
    {
        return $predicate($this->value) ? $this : new None();
    }

    public function getOrElse(mixed $default): mixed
    {
        return $this->value;
    }

    public function fold(\Closure $onNone, \Closure $onSome): mixed
    {
        return $onSome($this->value);
    }

    /**
     * @return Right<T>
     */
    public function toEither(mixed $ifNone): Either
    {
        return new Right($this->value);
    }

    /**
     * @return Success<T>
     */
    public function toTryTo(): TryTo
    {
        return new Success($this->value);
    }
}
