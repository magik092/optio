<?php

declare(strict_types=1);

namespace Optio\Tests\Tuple;

use Optio\Tuple\Tuple1;
use PHPUnit\Framework\TestCase;

final class Tuple1Test extends TestCase
{
    public function testToArray(): void
    {
        self::assertSame(['a'], (new Tuple1('a'))->toArray());
    }

    public function testAppendProducesTuple2(): void
    {
        $tuple2 = (new Tuple1('a'))->append('b');

        self::assertSame(['a', 'b'], $tuple2->toArray());
    }

    public function testPrependProducesTuple2(): void
    {
        $tuple2 = (new Tuple1('a'))->prepend('b');

        self::assertSame(['b', 'a'], $tuple2->toArray());
    }

    public function testConcatTuple0ReturnsSameShape(): void
    {
        $result = (new Tuple1('a'))->concatTuple0(new \Optio\Tuple\Tuple0());

        self::assertSame(['a'], $result->toArray());
    }

    public function testConcatTuple1ReturnsTuple2(): void
    {
        $result = (new Tuple1('a'))->concatTuple1(new Tuple1('b'));

        self::assertSame(['a', 'b'], $result->toArray());
    }
}
