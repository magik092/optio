<?php

declare(strict_types=1);

namespace Optio\Control;

use Optio\Control\TryTo\Failure;
use Optio\Control\TryTo\Success;

/**
 * Represents the result of a computation that may throw: either Success(value)
 * or Failure(\Throwable). Exceptions thrown inside map/flatMap callbacks are
 * caught automatically and turned into Failure.
 *
 * @template-covariant T
 */
abstract class TryTo
{
    /**
     * Runs the supplier and catches any \Throwable, turning it into a Failure.
     *
     * @template U
     *
     * @param \Closure(): U $supplier
     *
     * @return self<U>
     */
    public static function run(\Closure $supplier): self
    {
        try {
            return new Success($supplier());
        } catch (\Throwable $exception) {
            return new Failure($exception);
        }
    }

    abstract public function isSuccess(): bool;

    /**
     * Transforms the success value; if the mapper throws, the result becomes a Failure.
     *
     * @template U
     *
     * @param \Closure(T): U $mapper
     *
     * @return TryTo<U>
     */
    abstract public function map(\Closure $mapper): self;

    /**
     * Chains a TryTo-returning computation; if the mapper throws, the result becomes a Failure.
     *
     * @template U
     *
     * @param \Closure(T): TryTo<U> $mapper
     *
     * @return TryTo<U>
     */
    abstract public function flatMap(\Closure $mapper): self;

    /**
     * Turns a Failure into a Success by applying $recovery to the exception; no-op on Success.
     *
     * @template U
     *
     * @param \Closure(\Throwable): U $recovery
     *
     * @return TryTo<U>
     */
    abstract public function recover(\Closure $recovery): self;

    /**
     * Reduces to a single value: $onFailure is called with the exception, $onSuccess with the value.
     *
     * @template U
     *
     * @param \Closure(\Throwable): U $onFailure
     * @param \Closure(T): U          $onSuccess
     *
     * @return U
     */
    abstract public function fold(\Closure $onFailure, \Closure $onSuccess): mixed;

    /**
     * Converts to Option, discarding the exception: Success becomes Some, Failure becomes None.
     *
     * @return Option<T>
     */
    abstract public function toOption(): Option;

    /**
     * Converts to Either: Success becomes Right(value), Failure becomes Left(exception).
     *
     * @return Either<\Throwable, T>
     */
    abstract public function toEither(): Either;
}
