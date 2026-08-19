<?php

declare(strict_types=1);

namespace Optio\Tests\Stub;

final class NotHashableStub
{
    public function __construct(
        public readonly string $name,
        public readonly int $age,
    ) {
    }
}
