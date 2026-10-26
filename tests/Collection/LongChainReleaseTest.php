<?php

declare(strict_types=1);

namespace Optio\Tests\Collection;

use Optio\Collection\LinkedList;
use Optio\Collection\Stream;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;

/**
 * Freeing a long Cons chain used to recurse once per node inside the engine
 * and segfault the process at roughly 80k LinkedList / 30k Stream elements
 * (8 MB stack). Each test runs in its own process, so a regression shows up
 * as one failing test instead of killing the whole run.
 */
final class LongChainReleaseTest extends TestCase
{
    private const SIZE = 200_000;

    #[RunInSeparateProcess]
    public function testReleasingALongLinkedListDoesNotCrash(): void
    {
        $list = LinkedList::ofAll(range(1, self::SIZE));
        $length = $list->length();

        unset($list);

        self::assertSame(self::SIZE, $length);
    }

    #[RunInSeparateProcess]
    public function testReleasingAListKeepsTailsSharedWithOtherListsIntact(): void
    {
        $list = LinkedList::ofAll(range(1, self::SIZE));
        $longer = $list->prepend(0);
        $shorter = $list->tail()->tail();

        unset($list);

        self::assertSame(self::SIZE + 1, $longer->length());
        self::assertSame(self::SIZE * (self::SIZE + 1) / 2, array_sum($longer->toArray()));

        unset($longer);

        self::assertSame(self::SIZE - 2, $shorter->length());
        self::assertSame(3, $shorter->head());
        self::assertSame(self::SIZE * (self::SIZE + 1) / 2 - 3, array_sum($shorter->toArray()));
    }

    #[RunInSeparateProcess]
    public function testMapAndFilterOnALongLinkedListDoNotCrash(): void
    {
        $list = LinkedList::ofAll(range(1, self::SIZE));

        self::assertSame(self::SIZE, $list->map(fn (int $x): int => $x * 2)->length());
        self::assertSame(self::SIZE / 1000, $list->filter(fn (int $x): bool => $x % 1000 === 0)->length());
    }

    #[RunInSeparateProcess]
    public function testReleasingALongMaterializedStreamDoesNotCrash(): void
    {
        $stream = Stream::ofAll(range(1, self::SIZE));
        $count = count($stream->toArray());

        unset($stream);

        self::assertSame(self::SIZE, $count);
    }

    #[RunInSeparateProcess]
    public function testTakingALongPrefixOfAnInfiniteStreamDoesNotCrash(): void
    {
        self::assertCount(self::SIZE, Stream::from(1)->take(self::SIZE)->toArray());
    }
}
