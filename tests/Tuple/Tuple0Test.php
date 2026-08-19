<?php

declare(strict_types=1);

namespace Optio\Tests\Tuple;

use Optio\Tuple\Tuple0;
use PHPUnit\Framework\TestCase;

final class Tuple0Test extends TestCase
{
    public function testArityIsZero(): void
    {
        self::assertSame(0, (new Tuple0())->arity());
    }

    public function testToArrayIsEmpty(): void
    {
        self::assertSame([], (new Tuple0())->toArray());
    }

    public function testAppendProducesTuple1(): void
    {
        $tuple1 = (new Tuple0())->append('a');

        self::assertSame(1, $tuple1->arity());
        self::assertSame(['a'], $tuple1->toArray());
    }

    public function testPrependProducesTuple1(): void
    {
        $tuple1 = (new Tuple0())->prepend('a');

        self::assertSame(1, $tuple1->arity());
        self::assertSame(['a'], $tuple1->toArray());
    }
}
