<?php

declare(strict_types=1);

namespace Optio\Control\Validation;

use Optio\Control\Either;
use Optio\Control\Either\Left;
use Optio\Control\Option;
use Optio\Control\Validation;

/**
 * Validation variant holding one or more accumulated errors.
 *
 * @template-covariant E
 *
 * @extends Validation<E, never>
 */
final class Invalid extends Validation
{
    /**
     * @param list<E> $errors
     */
    public function __construct(private readonly array $errors)
    {
    }

    public function isValid(): bool
    {
        return false;
    }

    public function map(\Closure $mapper): Validation
    {
        return $this;
    }

    public function mapError(\Closure $mapper): Validation
    {
        return new self(array_map($mapper, $this->errors));
    }

    public function flatMap(\Closure $mapper): Validation
    {
        return $this;
    }

    public function fold(\Closure $onInvalid, \Closure $onValid): mixed
    {
        return $onInvalid($this->errors);
    }

    public function toEither(): Either
    {
        return new Left($this->errors);
    }

    public function toOption(): Option
    {
        return Option::none();
    }
}
