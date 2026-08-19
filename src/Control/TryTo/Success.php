<?php

declare(strict_types=1);

namespace Optio\Control\TryTo;

use Optio\Control\Either;
use Optio\Control\Either\Right;
use Optio\Control\Option;
use Optio\Control\TryTo;

/**
 * TryTo variant holding a successfully computed value.
 *
 * @template-covariant T
 *
 * @extends TryTo<T>
 */
final class Success extends TryTo
{
    /**
     * @param T $value
     */
    public function __construct(private readonly mixed $value)
    {
    }

    public function isSuccess(): bool
    {
        return true;
    }

    /**
     * @template U
     *
     * @param \Closure(T): U $mapper
     *
     * @return Success<U>|Failure
     */
    public function map(\Closure $mapper): TryTo
    {
        try {
            return new self($mapper($this->value));
        } catch (\Throwable $exception) {
            return new Failure($exception);
        }
    }

    /**
     * @template U
     *
     * @param \Closure(T): TryTo<U> $mapper
     *
     * @return TryTo<U>
     */
    public function flatMap(\Closure $mapper): TryTo
    {
        try {
            return $mapper($this->value);
        } catch (\Throwable $exception) {
            return new Failure($exception);
        }
    }

    /**
     * @template U
     *
     * @param \Closure(\Throwable): U $recovery
     *
     * @return self<T>
     */
    public function recover(\Closure $recovery): TryTo
    {
        return $this;
    }

    /**
     * @template U
     *
     * @param \Closure(\Throwable): U $onFailure
     * @param \Closure(T): U          $onSuccess
     *
     * @return U
     */
    public function fold(
        \Closure $onFailure,
        \Closure $onSuccess,
    ): mixed {
        return $onSuccess($this->value);
    }

    /**
     * @return Option<T>
     */
    public function toOption(): Option
    {
        return Option::some($this->value);
    }

    /**
     * @return Either<\Throwable, T>
     */
    public function toEither(): Either
    {
        return new Right($this->value);
    }
}
