<?php

declare(strict_types=1);

namespace Optio\Tests\Collection;

use Optio\Collection\LinkedList;
use Optio\Collection\LinkedList\Cons;
use Optio\Collection\LinkedList\Nil;
use Optio\Exception\HashableContractException;
use Optio\Exception\NoSuchElementException;
use Optio\Tests\Stub\HashableStub;
use PHPUnit\Framework\TestCase;

final class LinkedListTest extends TestCase
{
    public function testEmptyIsEmpty(): void
    {
        self::assertTrue(LinkedList::empty()->isEmpty());
        self::assertSame(0, LinkedList::empty()->length());
    }

    public function testNilConstructedDirectlyIsEmpty(): void
    {
        self::assertTrue((new Nil())->isEmpty());
    }

    public function testConsConstructedDirectlyIsNotEmpty(): void
    {
        self::assertFalse((new Cons(1, new Nil()))->isEmpty());
    }

    public function testOfCreatesListFromVariadicArguments(): void
    {
        $list = LinkedList::of('a', 'b', 'c');

        self::assertSame(3, $list->length());
        self::assertSame(['a', 'b', 'c'], $list->toArray());
    }

    public function testOfAllCreatesListFromIterablePreservingOrder(): void
    {
        $list = LinkedList::ofAll(['x', 'y', 'z']);

        self::assertSame(['x', 'y', 'z'], $list->toArray());
    }

    public function testHeadReturnsFirstElement(): void
    {
        self::assertSame('a', LinkedList::of('a', 'b')->head());
    }

    public function testHeadOnEmptyThrows(): void
    {
        $this->expectException(NoSuchElementException::class);

        LinkedList::empty()->head();
    }

    public function testTailReturnsRemainingElements(): void
    {
        self::assertSame(['b', 'c'], LinkedList::of('a', 'b', 'c')->tail()->toArray());
    }

    public function testTailOnEmptyThrows(): void
    {
        $this->expectException(NoSuchElementException::class);

        LinkedList::empty()->tail();
    }

    public function testPrependAddsToTheFront(): void
    {
        $list = LinkedList::of(2, 3)->prepend(1);

        self::assertSame([1, 2, 3], $list->toArray());
    }

    public function testPrependIsImmutableDoesNotMutateTheOriginal(): void
    {
        $original = LinkedList::of(2, 3);
        $original->prepend(1);

        self::assertSame([2, 3], $original->toArray());
    }

    public function testLengthIsConstantTimeCachedAtConstruction(): void
    {
        self::assertSame(3, LinkedList::of(1, 2, 3)->length());
        self::assertSame(4, LinkedList::of(1, 2, 3)->prepend(0)->length());
    }

    public function testReverseReversesOrder(): void
    {
        self::assertSame([3, 2, 1], LinkedList::of(1, 2, 3)->reverse()->toArray());
    }

    public function testReverseOfEmptyIsEmpty(): void
    {
        self::assertTrue(LinkedList::empty()->reverse()->isEmpty());
    }

    public function testToArrayPreservesOrder(): void
    {
        self::assertSame([1, 2, 3], LinkedList::of(1, 2, 3)->toArray());
    }

    public function testMapTransformsEachElementPreservingOrder(): void
    {
        self::assertSame([10, 20, 30], LinkedList::of(1, 2, 3)->map(fn (int $v): int => $v * 10)->toArray());
    }

    public function testFilterKeepsOnlyMatchingElements(): void
    {
        self::assertSame([2, 4], LinkedList::of(1, 2, 3, 4)->filter(fn (int $v): bool => $v % 2 === 0)->toArray());
    }

    public function testFoldCombinesAllElements(): void
    {
        self::assertSame(6, LinkedList::of(1, 2, 3)->fold(0, fn (int $acc, int $v): int => $acc + $v));
    }

    public function testForEachVisitsEveryElementInOrder(): void
    {
        $visited = [];
        LinkedList::of(1, 2, 3)->forEach(function (int $v) use (&$visited): void {
            $visited[] = $v;
        });

        self::assertSame([1, 2, 3], $visited);
    }

    public function testCountableReturnsLength(): void
    {
        self::assertCount(3, LinkedList::of(1, 2, 3));
    }

    public function testIteratorAggregateYieldsElementsInOrder(): void
    {
        $collected = [];
        foreach (LinkedList::of(1, 2, 3) as $element) {
            $collected[] = $element;
        }

        self::assertSame([1, 2, 3], $collected);
    }

    public function testOfAllIgnoresKeysAndKeepsIterationOrder(): void
    {
        self::assertSame([1, 2, 3], LinkedList::ofAll(['x' => 1, 'y' => 2, 'z' => 3])->toArray());
    }

    public function testOfAllAcceptsAGenerator(): void
    {
        $generator = (static function (): \Generator {
            yield 'a' => 1;
            yield 'a' => 2;
            yield 'b' => 3;
        })();

        self::assertSame([1, 2, 3], LinkedList::ofAll($generator)->toArray());
    }

    public function testDistinctKeepsFirstOccurrenceOrder(): void
    {
        self::assertSame([3, 1, 2], LinkedList::of(3, 1, 3, 2, 1)->distinct()->toArray());
    }

    public function testDistinctByKeepsFirstElementWithKey(): void
    {
        $result = LinkedList::of(['id' => 1, 'v' => 'a'], ['id' => 1, 'v' => 'b'])
            ->distinctBy(static fn (array $row): int => $row['id']);

        self::assertSame([['id' => 1, 'v' => 'a']], $result->toArray());
    }

    public function testDistinctIsStrictAcrossScalarTypes(): void
    {
        self::assertSame([1, true, '1', 1.0], LinkedList::of(1, true, '1', 1.0)->distinct()->toArray());
    }

    public function testDistinctOnArraysThrowsHashableContractException(): void
    {
        $this->expectException(HashableContractException::class);

        LinkedList::of([1], [1])->distinct();
    }

    public function testDistinctByJsonEncodeWorksOnArrays(): void
    {
        self::assertSame([[1], [2]], LinkedList::of([1], [2], [1])->distinctBy(json_encode(...))->toArray());
    }

    public function testDistinctWithNullElementThrowsHashableContractException(): void
    {
        $this->expectException(HashableContractException::class);

        LinkedList::of(1, null)->distinct();
    }

    public function testDistinctByHashableKeyDeduplicatesEqualObjects(): void
    {
        $result = LinkedList::of(['k' => new HashableStub('x'), 'tag' => 'first'], ['k' => new HashableStub('x'), 'tag' => 'second'])
            ->distinctBy(static fn (array $row): HashableStub => $row['k']);

        self::assertSame(1, $result->length());
        self::assertSame('first', $result->head()['tag']);
    }

    public function testDistinctOnEmptyAndSingleElement(): void
    {
        self::assertSame([], LinkedList::empty()->distinct()->toArray());
        self::assertSame([7], LinkedList::of(7)->distinct()->toArray());
    }

    public function testDistinctLeavesOriginalUnchanged(): void
    {
        $original = LinkedList::of(1, 1, 2);
        $original->distinct();

        self::assertSame([1, 1, 2], $original->toArray());
    }

    public function testDistinctOnLargeListDoesNotRecurse(): void
    {
        self::assertSame(50_000, LinkedList::ofAll(range(1, 50_000))->distinct()->length());
    }
}
