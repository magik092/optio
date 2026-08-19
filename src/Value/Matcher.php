<?php

declare(strict_types=1);

namespace Optio\Value;

use Optio\Exception\MatchNotFoundException;
use Optio\Exception\MultipleDefaultCasesException;

/**
 * Runtime pattern matching by type: dispatches to the first case() whose
 * class matches the value's actual type, or to default() if none matches.
 * Exhaustiveness (verifying at analysis time that every variant of a type
 * is covered) is intentionally out of scope — see the design spec's
 * roadmap ("phpstan-optio", a future tooling package), not this class.
 */
final class Matcher
{
    /**
     * @param list<array{0: class-string, 1: \Closure}> $cases
     */
    private function __construct(
        private readonly mixed $value,
        private readonly array $cases,
        private readonly ?\Closure $default,
    ) {
    }

    public static function value(mixed $value): self
    {
        return new self($value, [], null);
    }

    /**
     * @param class-string $class
     */
    public function case(string $class, \Closure $handler): self
    {
        return new self($this->value, [...$this->cases, [$class, $handler]], $this->default);
    }

    /**
     * @throws MultipleDefaultCasesException if default() was already called on this chain
     */
    public function default(\Closure $handler): self
    {
        if ($this->default !== null) {
            throw new MultipleDefaultCasesException('Matcher::default() can only be called once per chain.');
        }

        return new self($this->value, $this->cases, $handler);
    }

    /**
     * @throws MatchNotFoundException if no case matched and no default was registered
     */
    public function get(): mixed
    {
        foreach ($this->cases as [$class, $handler]) {
            if ($this->value instanceof $class) {
                return $handler($this->value);
            }
        }

        if ($this->default !== null) {
            return ($this->default)($this->value);
        }

        throw new MatchNotFoundException(sprintf('No case matched a value of type %s and no default() was provided.', get_debug_type($this->value)));
    }
}
