<?php

declare(strict_types=1);

namespace Optio\Tests\Collection;

use Optio\Collection\LinkedHashSet;
use PHPUnit\Framework\TestCase;

final class LinkedHashSetTest extends TestCase
{
    public function testEmptyHasLengthZero(): void
    {
        self::assertSame(0, LinkedHashSet::empty()->length());
        self::assertTrue(LinkedHashSet::empty()->isEmpty());
    }

    public function testOfCreatesSetFromVariadicArguments(): void
    {
        $set = LinkedHashSet::of('a', 'b', 'c');

        self::assertSame(3, $set->length());
        self::assertSame(['a', 'b', 'c'], $set->toArray());
    }

    public function testOfAllDeduplicatesWhileKeepingFirstOccurrencesPosition(): void
    {
        $set = LinkedHashSet::ofAll(['a', 'b', 'a', 'c']);

        self::assertSame(3, $set->length());
        self::assertSame(['a', 'b', 'c'], $set->toArray());
    }

    public function testAddAppendsNewElementsAtTheEnd(): void
    {
        $set = LinkedHashSet::of('a', 'b')->add('c');

        self::assertSame(['a', 'b', 'c'], $set->toArray());
    }

    public function testAddingAnExistingElementDoesNotMoveItOrGrow(): void
    {
        $set = LinkedHashSet::of('a', 'b', 'c')->add('a');

        self::assertSame(3, $set->length());
        self::assertSame(['a', 'b', 'c'], $set->toArray());
    }

    public function testRemoveThenReaddMovesTheElementToTheEnd(): void
    {
        $set = LinkedHashSet::of('a', 'b', 'c')->remove('a')->add('a');

        self::assertSame(['b', 'c', 'a'], $set->toArray());
    }

    public function testContains(): void
    {
        $set = LinkedHashSet::of('a');

        self::assertTrue($set->contains('a'));
        self::assertFalse($set->contains('b'));
    }

    public function testMapFilterFoldForEachPreserveOrder(): void
    {
        $set = LinkedHashSet::of(1, 2, 3);

        self::assertSame([2, 4, 6], $set->map(fn (int $n): int => $n * 2)->toArray());
        self::assertSame([1, 3], $set->filter(fn (int $n): bool => $n % 2 !== 0)->toArray());
        self::assertSame(6, $set->fold(0, fn (int $acc, int $n): int => $acc + $n));

        $seen = [];
        $set->forEach(function (int $n) use (&$seen): void {
            $seen[] = $n;
        });
        self::assertSame([1, 2, 3], $seen);
    }

    public function testSlidingAndGroupedFollowInsertionOrder(): void
    {
        $set = LinkedHashSet::of(1, 2, 3, 4);

        $windows = $set->sliding(2, 1);
        self::assertSame([1, 2], $windows->get(0)->toArray());
        self::assertSame([3, 4], $windows->get(2)->toArray());

        $groups = $set->grouped(2);
        self::assertSame(2, $groups->length());
        self::assertSame([1, 2], $groups->get(0)->toArray());
        self::assertSame([3, 4], $groups->get(1)->toArray());
    }
}
