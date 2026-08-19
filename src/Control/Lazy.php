<?php

declare(strict_types=1);

namespace Optio\Control;

/**
 * Wraps a computation that is deferred until first accessed and then memoized.
 *
 * @template-covariant T
 */
final class Lazy
{
    private bool $evaluated = false;

    private mixed $value = null;

    private ?\Closure $supplier;

    /**
     * @param \Closure(): T $supplier
     */
    private function __construct(\Closure $supplier)
    {
        $this->supplier = $supplier;
    }

    /**
     * @template U
     *
     * @param \Closure(): U $supplier
     *
     * @return self<U>
     */
    public static function of(\Closure $supplier): self
    {
        return new self($supplier);
    }

    /**
     * Evaluates the supplier on first call and caches the result; subsequent
     * calls return the memoized value without re-running the supplier.
     *
     * @return T
     */
    public function get(): mixed
    {
        if (!$this->evaluated && null !== $this->supplier) {
            $this->value = ($this->supplier)();
            $this->evaluated = true;
            $this->supplier = null;
        }

        return $this->value;
    }

    public function isEvaluated(): bool
    {
        return $this->evaluated;
    }

    /**
     * @template U
     *
     * @param \Closure(T): U $mapper
     *
     * @return self<U>
     */
    public function map(\Closure $mapper): self
    {
        return self::of(fn (): mixed => $mapper($this->get()));
    }
}
