<?php

declare(strict_types=1);

namespace Optio\Tests\Stub;

use Optio\Value\Comparable;
use Optio\Value\Hashable;

final class HashableStub implements Comparable, Hashable
{
    public function __construct(private readonly string $key)
    {
    }

    public function hashCode(): string
    {
        return $this->key;
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof self && $this->key === $other->key;
    }
}
