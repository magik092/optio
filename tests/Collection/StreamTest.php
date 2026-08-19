<?php

declare(strict_types=1);

namespace Optio\Tests\Collection;

use Optio\Collection\Stream;
use Optio\Exception\NoSuchElementException;
use PHPUnit\Framework\TestCase;

final class StreamTest extends TestCase
{
    public function testEmptyIsEmpty(): void
    {
        self::assertTrue(Stream::empty()->isEmpty());
    }

    public function testHeadOnEmptyThrows(): void
    {
        $this->expectException(NoSuchElementException::class);

        Stream::empty()->head();
    }

    public function testTailOnEmptyThrows(): void
    {
        $this->expectException(NoSuchElementException::class);

        Stream::empty()->tail();
    }

    public function testOfCreatesStreamFromVariadicArguments(): void
    {
        $stream = Stream::of(1, 2, 3);

        self::assertFalse($stream->isEmpty());
        self::assertSame(1, $stream->head());
        self::assertSame(2, $stream->tail()->head());
        self::assertSame(3, $stream->tail()->tail()->head());
        self::assertTrue($stream->tail()->tail()->tail()->isEmpty());
    }

    public function testOfAllCreatesStreamFromIterable(): void
    {
        $stream = Stream::ofAll(['a', 'b']);

        self::assertSame('a', $stream->head());
        self::assertSame('b', $stream->tail()->head());
        self::assertTrue($stream->tail()->tail()->isEmpty());
    }

    public function testOfAllFromAnEmptyIterableIsEmpty(): void
    {
        self::assertTrue(Stream::ofAll([])->isEmpty());
    }

    public function testOfAllConsumesAGeneratorLazily(): void
    {
        $consumed = [];
        $generator = (function () use (&$consumed) {
            foreach ([1, 2, 3] as $value) {
                $consumed[] = $value;
                yield $value;
            }
        })();

        $stream = Stream::ofAll($generator);

        // Only the first element should have been pulled from the generator
        // to build the head — the rest stays behind the Lazy tail.
        self::assertSame([1], $consumed);
        self::assertSame(1, $stream->head());
    }

    public function testFromBuildsAnInfiniteStreamOfConsecutiveIntegers(): void
    {
        $stream = Stream::from(5);

        self::assertSame(5, $stream->head());
        self::assertSame(6, $stream->tail()->head());
        self::assertSame(7, $stream->tail()->tail()->head());
    }

    public function testIterateBuildsAnInfiniteStreamFromASeedFunction(): void
    {
        $stream = Stream::iterate(1, fn (int $n): int => $n * 2);

        self::assertSame(1, $stream->head());
        self::assertSame(2, $stream->tail()->head());
        self::assertSame(4, $stream->tail()->tail()->head());
        self::assertSame(8, $stream->tail()->tail()->tail()->head());
    }

    public function testContinuallyBuildsAnInfiniteStreamFromASupplier(): void
    {
        $calls = 0;
        $stream = Stream::continually(function () use (&$calls): int {
            ++$calls;

            return 42;
        });

        self::assertSame(42, $stream->head());
        self::assertSame(42, $stream->tail()->head());
        // Only two heads were ever forced, so the supplier ran exactly twice —
        // proves the stream is not eagerly materialized ahead of demand.
        self::assertSame(2, $calls);
    }

    public function testTakeReturnsAFinitePrefixOfAnInfiniteStream(): void
    {
        $prefix = Stream::from(1)->take(3);

        self::assertSame(1, $prefix->head());
        self::assertSame(2, $prefix->tail()->head());
        self::assertSame(3, $prefix->tail()->tail()->head());
        self::assertTrue($prefix->tail()->tail()->tail()->isEmpty());
    }

    public function testTakeZeroOrLessReturnsEmpty(): void
    {
        self::assertTrue(Stream::from(1)->take(0)->isEmpty());
        self::assertTrue(Stream::from(1)->take(-1)->isEmpty());
    }

    public function testTakeMoreThanAvailableReturnsWhatExists(): void
    {
        $prefix = Stream::of(1, 2)->take(10);

        self::assertSame([1, 2], [$prefix->head(), $prefix->tail()->head()]);
        self::assertTrue($prefix->tail()->tail()->isEmpty());
    }

    public function testConsConstructedDirectlyIsNotEmpty(): void
    {
        self::assertFalse((new Stream\Cons(1, fn () => Stream::empty()))->isEmpty());
    }

    public function testNilConstructedDirectlyIsEmpty(): void
    {
        self::assertTrue((new Stream\Nil())->isEmpty());
    }
}
