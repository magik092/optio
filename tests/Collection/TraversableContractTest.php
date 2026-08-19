<?php

declare(strict_types=1);

namespace Optio\Tests\Collection;

use Optio\Collection\HashSet;
use Optio\Collection\LinkedList;
use Optio\Collection\Stream;
use Optio\Collection\Traversable;
use Optio\Collection\Vector;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Polymorphic contract tests: the same assertions run against every
 * Traversable implementation through the interface type, never a concrete
 * class. This is the kind of test that would have caught the HashMap
 * soundness gap from Plan 4 — a bug in one implementation's map/filter/fold
 * that a per-class test suite could miss because each class is only ever
 * tested against itself.
 */
final class TraversableContractTest extends TestCase
{
    /**
     * @return iterable<string, array{Traversable<int>}>
     */
    public static function collections(): iterable
    {
        yield 'Vector' => [Vector::of(1, 2, 3)];
        yield 'LinkedList' => [LinkedList::of(1, 2, 3)];
        yield 'HashSet' => [HashSet::of(1, 2, 3)];
        yield 'Stream' => [Stream::of(1, 2, 3)];
    }

    /**
     * @return iterable<string, array{Traversable<int>}>
     */
    public static function emptyCollections(): iterable
    {
        yield 'Vector' => [Vector::empty()];
        yield 'LinkedList' => [LinkedList::empty()];
        yield 'HashSet' => [HashSet::empty()];
        yield 'Stream' => [Stream::empty()];
    }

    /**
     * @param Traversable<int> $collection
     */
    #[DataProvider('collections')]
    public function testMapDoublesEachElement(Traversable $collection): void
    {
        $doubled = $collection->map(fn (int $v): int => $v * 2);
        $result = $doubled->toArray();
        sort($result);
        self::assertSame([2, 4, 6], $result);
    }

    /**
     * @param Traversable<int> $collection
     */
    #[DataProvider('collections')]
    public function testFilterKeepsOnlyMatchingElements(Traversable $collection): void
    {
        $filtered = $collection->filter(fn (int $v): bool => $v > 1);
        $result = $filtered->toArray();
        sort($result);
        self::assertSame([2, 3], $result);
    }

    /**
     * @param Traversable<int> $collection
     */
    #[DataProvider('collections')]
    public function testFoldSumsAllElements(Traversable $collection): void
    {
        $sum = $collection->fold(0, fn (int $acc, int $v): int => $acc + $v);
        self::assertSame(6, $sum);
    }

    /**
     * @param Traversable<int> $collection
     */
    #[DataProvider('collections')]
    public function testForEachVisitsEveryElement(Traversable $collection): void
    {
        $visited = [];
        $collection->forEach(function (int $v) use (&$visited): void {
            $visited[] = $v;
        });
        sort($visited);
        self::assertSame([1, 2, 3], $visited);
    }

    /**
     * @param Traversable<int> $collection
     */
    #[DataProvider('collections')]
    public function testToArrayContainsAllElements(Traversable $collection): void
    {
        $result = $collection->toArray();
        sort($result);
        self::assertSame([1, 2, 3], $result);
    }

    /**
     * @param Traversable<int> $collection
     */
    #[DataProvider('collections')]
    public function testLengthAndCountMatchNumberOfElements(Traversable $collection): void
    {
        self::assertSame(3, $collection->length());
        self::assertSame(3, $collection->count());
    }

    /**
     * @param Traversable<int> $collection
     */
    #[DataProvider('collections')]
    public function testIsEmptyIsFalseForNonEmptyCollection(Traversable $collection): void
    {
        self::assertFalse($collection->isEmpty());
    }

    /**
     * @param Traversable<int> $collection
     */
    #[DataProvider('collections')]
    public function testForeachIterationVisitsEveryElement(Traversable $collection): void
    {
        $visited = [];
        foreach ($collection as $value) {
            $visited[] = $value;
        }
        sort($visited);
        self::assertSame([1, 2, 3], $visited);
    }

    /**
     * @param Traversable<int> $collection
     */
    #[DataProvider('collections')]
    public function testGroupedPreservesAllElementsAcrossWindows(Traversable $collection): void
    {
        $windows = $collection->grouped(2);

        $totalElementsAcrossWindows = 0;
        $allElementsFromWindows = [];
        foreach ($windows as $window) {
            $totalElementsAcrossWindows += $window->length();
            foreach ($window->toArray() as $element) {
                $allElementsFromWindows[] = $element;
            }
        }

        self::assertSame($collection->length(), $totalElementsAcrossWindows);

        $expected = $collection->toArray();
        sort($expected);
        sort($allElementsFromWindows);
        self::assertSame($expected, $allElementsFromWindows);
    }

    /**
     * @param Traversable<int> $collection
     */
    #[DataProvider('emptyCollections')]
    public function testEmptyCollectionIsEmpty(Traversable $collection): void
    {
        self::assertTrue($collection->isEmpty());
        self::assertSame(0, $collection->length());
        self::assertSame(0, $collection->count());
        self::assertSame([], $collection->toArray());
    }
}
