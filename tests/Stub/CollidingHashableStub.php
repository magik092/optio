<?php

declare(strict_types=1);

namespace Optio\Tests\Stub;

use Optio\Value\Comparable;
use Optio\Value\Hashable;

final class CollidingHashableStub implements Comparable, Hashable
{
    public function __construct(public readonly string $id)
    {
    }

    public function hashCode(): string
    {
        return 'constant-colliding-hash';
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof self && $this->id === $other->id;
    }
}
