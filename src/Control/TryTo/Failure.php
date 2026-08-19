<?php

declare(strict_types=1);

namespace Optio\Control\TryTo;

use Optio\Control\Either;
use Optio\Control\Either\Left;
use Optio\Control\Option;
use Optio\Control\TryTo;

/**
 * TryTo variant holding a caught \Throwable.
 *
 * @extends TryTo<never>
 */
final class Failure extends TryTo
{
    public function __construct(private readonly \Throwable $exception)
    {
    }

    public function isSuccess(): bool
    {
        return false;
    }

    /**
     * @template U
     *
     * @param \Closure(never): U $mapper
     *
     * @return TryTo<U>
     */
    public function map(\Closure $mapper): TryTo
    {
        return $this;
    }

    /**
     * @template U
     *
     * @param \Closure(never): TryTo<U> $mapper
     *
     * @return TryTo<U>
     */
    public function flatMap(\Closure $mapper): TryTo
    {
        return $this;
    }

    /**
     * @template U
     *
     * @param \Closure(\Throwable): U $recovery
     *
     * @return Success<U>|Failure
     */
    public function recover(\Closure $recovery): TryTo
    {
        try {
            return new Success($recovery($this->exception));
        } catch (\Throwable $exception) {
            return new Failure($exception);
        }
    }

    /**
     * @template U
     *
     * @param \Closure(\Throwable): U $onFailure
     * @param \Closure(never): U      $onSuccess
     *
     * @return U
     */
    public function fold(\Closure $onFailure, \Closure $onSuccess): mixed
    {
        return $onFailure($this->exception);
    }

    /**
     * @return Option<never>
     */
    public function toOption(): Option
    {
        return Option::none();
    }

    /**
     * @return Either<\Throwable, never>
     */
    public function toEither(): Either
    {
        return new Left($this->exception);
    }
}
