<?php

declare(strict_types=1);

namespace Optio\Control\Validation;

use Optio\Control\Either;
use Optio\Control\Either\Right;
use Optio\Control\Option;
use Optio\Control\Validation;

/**
 * Validation variant holding a successfully validated value.
 *
 * @template-covariant T
 *
 * @extends Validation<never, T>
 */
final class Valid extends Validation
{
    /**
     * @param T $value
     */
    public function __construct(private readonly mixed $value)
    {
    }

    public function isValid(): bool
    {
        return true;
    }

    public function map(\Closure $mapper): Validation
    {
        return new self($mapper($this->value));
    }

    public function mapError(\Closure $mapper): Validation
    {
        return $this;
    }

    public function flatMap(\Closure $mapper): Validation
    {
        return $mapper($this->value);
    }

    public function fold(\Closure $onInvalid, \Closure $onValid): mixed
    {
        return $onValid($this->value);
    }

    public function toEither(): Either
    {
        return new Right($this->value);
    }

    public function toOption(): Option
    {
        return Option::some($this->value);
    }
}
