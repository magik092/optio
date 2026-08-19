<?php

declare(strict_types=1);

namespace Optio\Control\Either;

use Optio\Control\Either;
use Optio\Control\Option;

/**
 * Either variant conventionally holding a success value.
 *
 * @template-covariant R
 *
 * @extends Either<never, R>
 */
final class Right extends Either
{
    /**
     * @param R $value
     */
    public function __construct(private readonly mixed $value)
    {
    }

    public function isRight(): bool
    {
        return true;
    }

    /**
     * @template U
     *
     * @param \Closure(R): U $mapper
     *
     * @return Right<U>
     */
    public function map(\Closure $mapper): Either
    {
        return new self($mapper($this->value));
    }

    /**
     * @template L
     * @template U
     *
     * @param \Closure(R): Either<L, U> $mapper
     *
     * @return Either<L, U>
     */
    public function flatMap(\Closure $mapper): Either
    {
        return $mapper($this->value);
    }

    /**
     * @template U
     *
     * @param \Closure(never): U $onLeft
     * @param \Closure(R): U     $onRight
     *
     * @return U
     */
    public function fold(\Closure $onLeft, \Closure $onRight): mixed
    {
        return $onRight($this->value);
    }

    /**
     * @return Left<R>
     */
    public function swap(): Either
    {
        return new Left($this->value);
    }

    public function toOption(): Option
    {
        return Option::some($this->value);
    }
}
