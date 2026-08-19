<?php

declare(strict_types=1);

namespace Optio\Tests\Collection;

use Optio\Collection\Stream;
use PHPUnit\Framework\TestCase;

final class StreamInfiniteSafetyTest extends TestCase
{
    public function testMapOnAnInfiniteStreamTerminatesImmediately(): void
    {
        $mapped = Stream::from(1)->map(fn (int $v): int => $v * 2);

        self::assertSame(2, $mapped->head());
    }

    public function testFilterOnAnInfiniteStreamTerminatesWhenAMatchExists(): void
    {
        $filtered = Stream::from(1)->filter(fn (int $v): bool => $v > 1000);

        self::assertSame(1001, $filtered->head());
    }

    public function testMapOnAnInfiniteStreamOnlyInvokesTheMapperForConsumedElements(): void
    {
        $calls = 0;
        $result = Stream::from(1)
            ->map(function (int $v) use (&$calls): int {
                ++$calls;

                return $v * 2;
            })
            ->take(3)
            ->toArray();

        self::assertSame([2, 4, 6], $result);
        // Exactly the 3 consumed elements were mapped — take() no longer
        // overpulls one extra element from the source before stopping.
        self::assertSame(3, $calls);
    }

    public function testFilterOnAnInfiniteStreamOnlyInvokesThePredicateUpToTheMatch(): void
    {
        $calls = 0;
        $filtered = Stream::from(1)->filter(function (int $v) use (&$calls): bool {
            ++$calls;

            return $v > 1000;
        });

        self::assertSame(1001, $filtered->head());
        // The predicate was evaluated for 1..1001 (1000 rejections, 1 match)
        // and never beyond the first matching element.
        self::assertSame(1001, $calls);
    }

    public function testTakeOnAnInfiniteStreamForcesExactlyNElementsFromTheSource(): void
    {
        $calls = 0;
        $stream = Stream::continually(function () use (&$calls): int {
            ++$calls;

            return 42;
        });

        $result = $stream->take(2)->toArray();

        self::assertSame([42, 42], $result);
        // Before finding 1's fix, take() forced one extra element from the
        // source (3 supplier calls for 2 kept elements). It must now force
        // exactly as many source elements as it keeps.
        self::assertSame(2, $calls);
    }

    public function testTakeThenToArrayOnAnInfiniteStreamMaterializesOnlyThePrefix(): void
    {
        $prefix = Stream::from(1)->take(1000)->toArray();

        self::assertCount(1000, $prefix);
        self::assertSame(1, $prefix[0]);
        self::assertSame(1000, $prefix[999]);
    }

    public function testChainingMapFilterTakeOnAnInfiniteStreamStaysFast(): void
    {
        $result = Stream::from(1)
            ->map(fn (int $v): int => $v * 2)
            ->filter(fn (int $v): bool => $v % 3 === 0)
            ->take(5)
            ->toArray();

        self::assertSame([6, 12, 18, 24, 30], $result);
    }

    public function testIterateOnAnInfiniteStreamWithTakeStaysFast(): void
    {
        $powers = Stream::iterate(1, fn (int $n): int => $n * 2)->take(10)->toArray();

        self::assertSame([1, 2, 4, 8, 16, 32, 64, 128, 256, 512], $powers);
    }
}
