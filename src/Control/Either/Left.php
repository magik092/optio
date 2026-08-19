<?php

declare(strict_types=1);

namespace Optio\Control\Either;

use Optio\Control\Either;
use Optio\Control\Option;

/**
 * Either variant conventionally holding an error/failure value.
 *
 * @template-covariant L
 *
 * @extends Either<L, never>
 */
final class Left extends Either
{
    /**
     * @param L $value
     */
    public function __construct(private readonly mixed $value)
    {
    }

    public function isRight(): bool
    {
        return false;
    }

    /**
     * @template U
     *
     * @param \Closure(never): U $mapper
     *
     * @return Left<L>
     */
    public function map(\Closure $mapper): Either
    {
        return $this;
    }

    /**
     * @template UL
     * @template U
     *
     * @param \Closure(never): Either<UL, U> $mapper
     *
     * @return Left<L>
     */
    public function flatMap(\Closure $mapper): Either
    {
        return $this;
    }

    /**
     * @template U
     *
     * @param \Closure(L): U     $onLeft
     * @param \Closure(never): U $onRight
     *
     * @return U
     */
    public function fold(\Closure $onLeft, \Closure $onRight): mixed
    {
        return $onLeft($this->value);
    }

    /**
     * @return Right<L>
     */
    public function swap(): Either
    {
        return new Right($this->value);
    }

    public function toOption(): Option
    {
        return Option::none();
    }
}
