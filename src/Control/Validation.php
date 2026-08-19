<?php

declare(strict_types=1);

namespace Optio\Control;

use Optio\Control\Validation\Invalid;
use Optio\Control\Validation\Valid;

/**
 * Represents the result of a validation: either Valid(value) or Invalid(errors).
 * Unlike Either, Invalid holds a list of errors so failures from multiple
 * Validations can be accumulated via combine() instead of short-circuiting on
 * the first one.
 *
 * @template-covariant E
 * @template-covariant T
 */
abstract class Validation
{
    /**
     * @template U
     *
     * @param U $value
     *
     * @return self<never, U>
     */
    public static function valid(mixed $value): self
    {
        return new Valid($value);
    }

    /**
     * @template F
     *
     * @param F $error
     *
     * @return self<F, never>
     */
    public static function invalid(mixed $error): self
    {
        return new Invalid([$error]);
    }

    abstract public function isValid(): bool;

    /**
     * @template U
     *
     * @param \Closure(T): U $mapper
     *
     * @return self<E, U>
     */
    abstract public function map(\Closure $mapper): self;

    /**
     * @template F
     *
     * @param \Closure(E): F $mapper
     *
     * @return self<F, T>
     */
    abstract public function mapError(\Closure $mapper): self;

    /**
     * @template FE
     * @template U
     *
     * @param \Closure(T): self<FE, U> $mapper
     *
     * @return self<E|FE, U>
     */
    abstract public function flatMap(\Closure $mapper): self;

    /**
     * Reduces to a single value: $onInvalid is called with the error list, $onValid with the value.
     *
     * @template U
     *
     * @param \Closure(list<E>): U $onInvalid
     * @param \Closure(T): U       $onValid
     *
     * @return U
     */
    abstract public function fold(\Closure $onInvalid, \Closure $onValid): mixed;

    /**
     * Combines two Validations, accumulating errors instead of short-circuiting:
     * both Invalid merges both error lists; one Invalid propagates its errors;
     * both Valid applies $combiner to the two values.
     *
     * @template FE
     * @template U
     * @template V
     *
     * @param self<FE, U>       $other
     * @param \Closure(T, U): V $combiner
     *
     * @return self<E|FE, V>
     */
    public function combine(self $other, \Closure $combiner): self
    {
        return $this->fold(
            /**
             * @param list<E> $errors
             *
             * @return self<E|FE, V>
             */
            fn (array $errors): self => $this->combineWithInvalidLeft($errors, $other),
            /**
             * @param T $value
             *
             * @return self<E|FE, V>
             */
            fn (mixed $value): self => $this->combineWithValidLeft($value, $other, $combiner),
        );
    }

    /**
     * @template FE
     * @template U
     *
     * @param list<E>     $leftErrors
     * @param self<FE, U> $other
     *
     * @return self<E|FE, never>
     */
    private function combineWithInvalidLeft(array $leftErrors, self $other): self
    {
        return $other->fold(
            /**
             * @param list<FE> $rightErrors
             *
             * @return self<E|FE, never>
             */
            fn (array $rightErrors): self => new Invalid(array_merge($leftErrors, $rightErrors)),
            /**
             * @return self<E|FE, never>
             */
            fn (mixed $rightValue): self => new Invalid($leftErrors),
        );
    }

    /**
     * @template FE
     * @template U
     * @template V
     *
     * @param T                 $leftValue
     * @param self<FE, U>       $other
     * @param \Closure(T, U): V $combiner
     *
     * @return self<E|FE, V>
     */
    private function combineWithValidLeft(mixed $leftValue, self $other, \Closure $combiner): self
    {
        return $other->fold(
            /**
             * @param list<FE> $rightErrors
             *
             * @return self<E|FE, V>
             */
            fn (array $rightErrors): self => new Invalid($rightErrors),
            /**
             * @param U $rightValue
             *
             * @return self<E|FE, V>
             */
            fn (mixed $rightValue): self => new Valid($combiner($leftValue, $rightValue)),
        );
    }

    /**
     * Converts to Either: Valid becomes Right(value), Invalid becomes Left(errors).
     *
     * @return Either<list<E>, T>
     */
    abstract public function toEither(): Either;

    /**
     * Converts to Option, discarding errors: Valid becomes Some, Invalid becomes None.
     *
     * @return Option<T>
     */
    abstract public function toOption(): Option;
}
