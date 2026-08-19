<?php

declare(strict_types=1);

namespace Optio\Control;

use Optio\Control\Option\None;
use Optio\Control\Option\Some;

/**
 * Represents an optional value: either Some(value) or None. Avoids null checks
 * by making absence explicit and composable via map/flatMap/fold.
 *
 * @template-covariant T
 */
abstract class Option
{
    /**
     * @template U
     *
     * @param U $value
     *
     * @return ($value is null ? None : Some<U>)
     */
    public static function of(mixed $value): self
    {
        return $value === null ? new None() : new Some($value);
    }

    /**
     * @template U
     *
     * @param U $value
     *
     * @return Some<U>
     */
    public static function some(mixed $value): self
    {
        return new Some($value);
    }

    /**
     * @return None
     */
    public static function none(): self
    {
        return new None();
    }

    abstract public function isDefined(): bool;

    /**
     * @template U
     *
     * @param \Closure(T): U $mapper
     *
     * @return self<U>
     */
    abstract public function map(\Closure $mapper): self;

    /**
     * @template U
     *
     * @param \Closure(T): self<U> $mapper
     *
     * @return self<U>
     */
    abstract public function flatMap(\Closure $mapper): self;

    /**
     * @param \Closure(T): bool $predicate
     *
     * @return self<T>
     */
    abstract public function filter(\Closure $predicate): self;

    /**
     * @template U
     *
     * @param U $default
     *
     * @return T|U
     */
    abstract public function getOrElse(mixed $default): mixed;

    /**
     * Reduces the Option to a single value: $onNone is called for None, $onSome for Some.
     *
     * @template U
     *
     * @param \Closure(): U  $onNone
     * @param \Closure(T): U $onSome
     *
     * @return U
     */
    abstract public function fold(\Closure $onNone, \Closure $onSome): mixed;

    /**
     * Converts to Either: Some becomes Right(value), None becomes Left($ifNone).
     *
     * @template U
     *
     * @param U $ifNone
     *
     * @return Either<U, T>
     */
    abstract public function toEither(mixed $ifNone): Either;

    /**
     * Converts to TryTo: Some becomes Success(value), None becomes Failure(NoSuchElementException).
     *
     * @return TryTo<T>
     */
    abstract public function toTryTo(): TryTo;
}
