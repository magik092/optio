<?php

declare(strict_types=1);

namespace Optio\Tests\Collection;

use Optio\Collection\HashSet;
use Optio\Collection\LinkedList;
use Optio\Collection\Vector;
use PHPUnit\Framework\TestCase;

final class SlidingGroupedTest extends TestCase
{
    public function testVectorSlidingWithOverlap(): void
    {
        $windows = Vector::of(1, 2, 3, 4, 5)->sliding(3, 1)->toArray();

        self::assertCount(5, $windows);
        self::assertSame([1, 2, 3], $windows[0]->toArray());
        self::assertSame([2, 3, 4], $windows[1]->toArray());
        self::assertSame([3, 4, 5], $windows[2]->toArray());
        self::assertSame([4, 5], $windows[3]->toArray());
        self::assertSame([5], $windows[4]->toArray());
    }

    public function testVectorGroupedNonOverlapping(): void
    {
        $windows = Vector::of(1, 2, 3, 4, 5)->grouped(2)->toArray();

        self::assertCount(3, $windows);
        self::assertSame([1, 2], $windows[0]->toArray());
        self::assertSame([3, 4], $windows[1]->toArray());
        self::assertSame([5], $windows[2]->toArray());
    }

    public function testVectorSlidingOnEmptyReturnsEmptyVector(): void
    {
        $result = Vector::empty()->sliding(2, 1);

        self::assertInstanceOf(Vector::class, $result);
        self::assertTrue($result->isEmpty());
    }

    public function testVectorSlidingWithNonPositiveSizeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Vector::of(1, 2, 3)->sliding(0, 1);
    }

    public function testLinkedListSlidingWithOverlapPreservesOrder(): void
    {
        $windows = LinkedList::of(1, 2, 3, 4)->sliding(2, 1)->toArray();

        self::assertCount(4, $windows);
        self::assertSame([1, 2], $windows[0]->toArray());
        self::assertSame([2, 3], $windows[1]->toArray());
        self::assertSame([3, 4], $windows[2]->toArray());
        self::assertSame([4], $windows[3]->toArray());
    }

    public function testLinkedListGroupedNonOverlapping(): void
    {
        $windows = LinkedList::of(1, 2, 3, 4, 5, 6)->grouped(3)->toArray();

        self::assertCount(2, $windows);
        self::assertSame([1, 2, 3], $windows[0]->toArray());
        self::assertSame([4, 5, 6], $windows[1]->toArray());
    }

    public function testLinkedListSlidingOnEmptyReturnsEmptyVector(): void
    {
        $result = LinkedList::empty()->sliding(2, 1);

        self::assertInstanceOf(Vector::class, $result);
        self::assertTrue($result->isEmpty());
    }

    public function testHashSetGroupedProducesCorrectNumberOfChunksAndCoversAllElements(): void
    {
        $set = HashSet::of(1, 2, 3, 4, 5);
        $windows = $set->grouped(2)->toArray();

        self::assertCount(3, $windows);

        // HashSet iteration order is hash-based, not insertion order, so
        // assert on the union of all elements across windows rather than
        // on any particular window's exact contents.
        $allElements = [];
        foreach ($windows as $window) {
            self::assertInstanceOf(HashSet::class, $window);
            foreach ($window->toArray() as $element) {
                $allElements[] = $element;
            }
        }
        sort($allElements);
        self::assertSame([1, 2, 3, 4, 5], $allElements);
    }

    public function testHashSetSlidingWithNonPositiveStepThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        HashSet::of(1, 2, 3)->sliding(2, 0);
    }

    public function testHashSetSlidingOnEmptyReturnsEmptyVector(): void
    {
        $result = HashSet::empty()->sliding(2, 1);

        self::assertInstanceOf(Vector::class, $result);
        self::assertTrue($result->isEmpty());
    }
}
