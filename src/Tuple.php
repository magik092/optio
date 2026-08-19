<?php

declare(strict_types=1);

namespace Optio;

use Optio\Exception\UnsupportedOperationException;
use Optio\Tuple\Tuple0;
use Optio\Tuple\Tuple1;
use Optio\Tuple\Tuple2;
use Optio\Tuple\Tuple3;
use Optio\Tuple\Tuple4;
use Optio\Tuple\Tuple5;
use Optio\Tuple\Tuple6;
use Optio\Tuple\Tuple7;
use Optio\Tuple\Tuple8;
use Optio\Value\Comparable;
use Optio\Value\Comparator;

/**
 * Base class for fixed-size, immutable, heterogeneous tuples (Tuple0..Tuple8).
 * Elements are accessed positionally via ArrayAccess (read-only) and equality
 * is structural, element-by-element via Comparator::equals().
 *
 * @implements \ArrayAccess<int, mixed>
 */
abstract class Tuple implements \ArrayAccess, Comparable
{
    public const TUPLE_MAX_SIZE = 8;

    public static function of(mixed ...$values): self
    {
        return match (count($values)) {
            0 => new Tuple0(),
            1 => new Tuple1(...$values),
            2 => new Tuple2(...$values),
            3 => new Tuple3(...$values),
            4 => new Tuple4(...$values),
            5 => new Tuple5(...$values),
            6 => new Tuple6(...$values),
            7 => new Tuple7(...$values),
            8 => new Tuple8(...$values),
            default => throw new \InvalidArgumentException('Invalid number of elements'),
        };
    }

    abstract public function arity(): int;

    /**
     * @return mixed[]
     */
    abstract public function toArray(): array;

    /**
     * @template T
     *
     * @param T $value
     */
    abstract public function append(mixed $value): self;

    /**
     * @template T
     *
     * @param T $value
     */
    abstract public function prepend(mixed $value): self;

    /**
     * Concatenates this tuple's elements with another's into a new, larger tuple.
     */
    public function concat(self $tuple): self
    {
        return self::of(...$this->toArray(), ...$tuple->toArray());
    }

    /**
     * Spreads the tuple's elements as positional arguments to $transformer.
     *
     * @template U
     *
     * @param \Closure(mixed...): U $transformer
     *
     * @return U
     */
    public function apply(\Closure $transformer): mixed
    {
        return call_user_func($transformer, ...$this->toArray());
    }

    public function map(\Closure $mapper): self
    {
        return self::of(...array_map($mapper, $this->toArray()));
    }

    public function offsetExists(mixed $offset): bool
    {
        return array_key_exists($offset, $this->toArray());
    }

    public function offsetGet(mixed $offset): mixed
    {
        $data = $this->toArray();
        if (!array_key_exists($offset, $data)) {
            throw new \OutOfRangeException("Undefined offset: {$offset}");
        }

        return $data[$offset];
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new UnsupportedOperationException('cannot change Tuple value with ArrayAccess');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new UnsupportedOperationException('cannot unset Tuple value');
    }

    public function equals(mixed $other): bool
    {
        if (!$other instanceof self) {
            return false;
        }

        if ($this->arity() !== $other->arity()) {
            return false;
        }

        foreach ($this->toArray() as $key => $value) {
            if (!Comparator::equals($value, $other->toArray()[$key])) {
                return false;
            }
        }

        return true;
    }
}
