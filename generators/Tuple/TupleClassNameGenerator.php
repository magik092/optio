<?php

declare(strict_types=1);

namespace Optio\Generators\Tuple;

class TupleClassNameGenerator
{
    public function forSize(string $namespace, int $size): string
    {
        return sprintf('%s\Tuple%s', $namespace, $size);
    }
}
