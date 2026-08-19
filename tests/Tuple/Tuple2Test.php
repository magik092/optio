<?php

declare(strict_types=1);

namespace Optio\Tests\Tuple;

use Optio\Tuple\Tuple1;
use Optio\Tuple\Tuple2;
use PHPUnit\Framework\TestCase;

final class Tuple2Test extends TestCase
{
    public function testToArray(): void
    {
        self::assertSame(['a', 1], (new Tuple2('a', 1))->toArray());
    }

    public function testArity(): void
    {
        self::assertSame(2, (new Tuple2('a', 1))->arity());
    }

    public function testAppend(): void
    {
        self::assertSame(['a', 1, true], (new Tuple2('a', 1))->append(true)->toArray());
    }

    public function testPrepend(): void
    {
        self::assertSame([true, 'a', 1], (new Tuple2('a', 1))->prepend(true)->toArray());
    }

    public function testConcatTuple1ReturnsTuple3(): void
    {
        $result = (new Tuple2('a', 1))->concatTuple1(new Tuple1(true));

        self::assertSame(['a', 1, true], $result->toArray());
    }

    public function testMap(): void
    {
        $result = (new Tuple2(1, 2))->map(fn (int $v) => $v * 10);

        self::assertSame([10, 20], $result->toArray());
    }

    public function testApply(): void
    {
        $result = (new Tuple2(1, 2))->apply(static function (mixed ...$values): int {
            /** @var array<int> $values */
            return array_sum($values);
        });

        self::assertSame(3, $result);
    }
}
