<?php

declare(strict_types=1);

namespace Optio\Control;

use Optio\Control\Either\Left;
use Optio\Control\Either\Right;

/**
 * Represents a value of one of two types: Left (conventionally an error/failure)
 * or Right (conventionally a success). Right-biased: map/flatMap operate on Right,
 * Left passes through unchanged.
 *
 * @template-covariant L
 * @template-covariant R
 */
abstract class Either
{
    /**
     * @template U
     *
     * @param U $value
     *
     * @return Left<U>
     */
    public static function left(mixed $value): self
    {
        return new Left($value);
    }

    /**
     * @template U
     *
     * @param U $value
     *
     * @return Right<U>
     */
    public static function right(mixed $value): self
    {
        return new Right($value);
    }

    abstract public function isRight(): bool;

    /**
     * @template U
     *
     * @param \Closure(R): U $mapper
     *
     * @return self<L, U>
     */
    abstract public function map(\Closure $mapper): self;

    /**
     * @template UL
     * @template U
     *
     * @param \Closure(R): self<UL, U> $mapper
     *
     * @return self<L|UL, U>
     */
    abstract public function flatMap(\Closure $mapper): self;

    /**
     * Reduces to a single value: $onLeft is called for Left, $onRight for Right.
     *
     * @template U
     *
     * @param \Closure(L): U $onLeft
     * @param \Closure(R): U $onRight
     *
     * @return U
     */
    abstract public function fold(\Closure $onLeft, \Closure $onRight): mixed;

    /**
     * Swaps Left and Right: a Left becomes a Right with the same value and vice versa.
     *
     * @return self<R, L>
     */
    abstract public function swap(): self;

    /**
     * Converts to Option, discarding the Left value: Right becomes Some, Left becomes None.
     *
     * @return Option<R>
     */
    abstract public function toOption(): Option;
}
