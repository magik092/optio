<?php

declare(strict_types=1);

namespace Optio\Tests\Tuple;

use Optio\Tuple;
use Optio\Tuple\Tuple8;
use PHPUnit\Framework\TestCase;

final class Tuple8Test extends TestCase
{
    public function testArity(): void
    {
        self::assertSame(8, (new Tuple8(1, 2, 3, 4, 5, 6, 7, 8))->arity());
    }

    public function testToArray(): void
    {
        self::assertSame([1, 2, 3, 4, 5, 6, 7, 8], (new Tuple8(1, 2, 3, 4, 5, 6, 7, 8))->toArray());
    }

    public function testTupleOfEightArgumentsReturnsTuple8(): void
    {
        $tuple = Tuple::of(1, 2, 3, 4, 5, 6, 7, 8);

        self::assertInstanceOf(Tuple8::class, $tuple);
    }
}
