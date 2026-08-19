<?php

declare(strict_types=1);

namespace Optio\Tests\Collection;

use Optio\Collection\Stream;
use PHPUnit\Framework\TestCase;

final class StreamTraversableTest extends TestCase
{
    public function testMapTransformsEachElementLazily(): void
    {
        $stream = Stream::of(1, 2, 3)->map(fn (int $v): int => $v * 10);

        self::assertSame([10, 20, 30], $stream->toArray());
    }

    public function testFilterKeepsOnlyMatchingElements(): void
    {
        $stream = Stream::of(1, 2, 3, 4, 5)->filter(fn (int $v): bool => $v % 2 === 0);

        self::assertSame([2, 4], $stream->toArray());
    }

    public function testFilterOnEmptyIsEmpty(): void
    {
        self::assertTrue(Stream::empty()->filter(fn (int $v): bool => true)->isEmpty());
    }

    public function testToArrayOnEmptyIsEmptyArray(): void
    {
        self::assertSame([], Stream::empty()->toArray());
    }

    public function testFoldCombinesAllElements(): void
    {
        self::assertSame(6, Stream::of(1, 2, 3)->fold(0, fn (int $acc, int $v): int => $acc + $v));
    }

    public function testForEachVisitsEveryElementInOrder(): void
    {
        $visited = [];
        Stream::of(1, 2, 3)->forEach(function (int $v) use (&$visited): void {
            $visited[] = $v;
        });

        self::assertSame([1, 2, 3], $visited);
    }

    public function testLengthCountsElements(): void
    {
        self::assertSame(3, Stream::of(1, 2, 3)->length());
        self::assertSame(0, Stream::empty()->length());
    }

    public function testCountableReturnsLength(): void
    {
        self::assertCount(3, Stream::of(1, 2, 3));
    }

    public function testIteratorAggregateYieldsElementsInOrder(): void
    {
        $collected = [];
        foreach (Stream::of(1, 2, 3) as $element) {
            $collected[] = $element;
        }

        self::assertSame([1, 2, 3], $collected);
    }

    public function testTheSpecMotivatingExampleSumOfFirstTenSquaresOfEvenNumbers(): void
    {
        // The exact example from the design spec / munusphp README, adapted
        // to Optio's fold() instead of a dedicated sum() method.
        $result = Stream::from(1)
            ->filter(fn (int $n): bool => $n % 2 === 0)
            ->map(fn (int $n): int => $n ** 2)
            ->take(10)
            ->fold(0, fn (int $acc, int $v): int => $acc + $v);

        // Sum of squares of 2,4,...,20 = 4+16+36+64+100+144+196+256+324+400
        self::assertSame(1540, $result);
    }

    public function testForeachBreakingEarlyNeverForcesMoreThanWhatWasConsumed(): void
    {
        $calls = 0;
        $stream = Stream::from(1)->map(function (int $v) use (&$calls): int {
            ++$calls;

            return $v;
        });

        $visited = [];
        foreach ($stream as $value) {
            $visited[] = $value;
            if (\count($visited) === 3) {
                break;
            }
        }

        self::assertSame([1, 2, 3], $visited);
        // Breaking after 3 elements must not have forced the mapper to run
        // on a 4th element behind the scenes.
        self::assertSame(3, $calls);
    }

    public function testSlidingProducesOverlappingWindowsOfStreams(): void
    {
        $windows = Stream::of(1, 2, 3, 4, 5)->sliding(3, 1);

        $result = array_map(
            fn (Stream $window): array => $window->toArray(),
            $windows->toArray(),
        );

        self::assertContainsOnlyInstancesOf(Stream::class, $windows->toArray());
        self::assertSame([[1, 2, 3], [2, 3, 4], [3, 4, 5], [4, 5], [5]], $result);
    }

    public function testGroupedProducesNonOverlappingChunksOfStreams(): void
    {
        $groups = Stream::of(1, 2, 3, 4, 5)->grouped(2);

        $result = array_map(
            fn (Stream $group): array => $group->toArray(),
            $groups->toArray(),
        );

        self::assertContainsOnlyInstancesOf(Stream::class, $groups->toArray());
        self::assertSame([[1, 2], [3, 4], [5]], $result);
    }
}
