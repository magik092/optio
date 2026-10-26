<?php

declare(strict_types=1);

namespace Optio\Tests\Collection;

use Optio\Collection\Vector;
use Optio\Exception\HashableContractException;
use Optio\Tests\Stub\HashableStub;
use PHPUnit\Framework\TestCase;

final class VectorTest extends TestCase
{
    public function testEmptyHasLengthZero(): void
    {
        self::assertSame(0, Vector::empty()->length());
        self::assertTrue(Vector::empty()->isEmpty());
    }

    public function testOfCreatesVectorFromVariadicArguments(): void
    {
        $vector = Vector::of('a', 'b', 'c');

        self::assertSame(3, $vector->length());
        self::assertSame('a', $vector->get(0));
        self::assertSame('b', $vector->get(1));
        self::assertSame('c', $vector->get(2));
    }

    public function testOfAllCreatesVectorFromIterable(): void
    {
        $vector = Vector::ofAll(['x', 'y']);

        self::assertSame(2, $vector->length());
        self::assertSame('x', $vector->get(0));
        self::assertSame('y', $vector->get(1));
    }

    public function testAppendReturnsNewVectorWithElementAtTheEnd(): void
    {
        $vector = Vector::of(1, 2)->append(3);

        self::assertSame(3, $vector->length());
        self::assertSame(3, $vector->get(2));
    }

    public function testAppendIsImmutableDoesNotMutateTheOriginal(): void
    {
        $original = Vector::of(1, 2);
        $original->append(3);

        self::assertSame(2, $original->length());
    }

    public function testGetOutOfRangeThrows(): void
    {
        $this->expectException(\OutOfRangeException::class);

        Vector::of(1, 2)->get(5);
    }

    public function testGetNegativeIndexThrows(): void
    {
        $this->expectException(\OutOfRangeException::class);

        Vector::of(1, 2)->get(-1);
    }

    public function testUpdateReplacesTheValueAtIndex(): void
    {
        $vector = Vector::of('a', 'b', 'c')->update(1, 'B');

        self::assertSame('B', $vector->get(1));
        self::assertSame('a', $vector->get(0));
        self::assertSame('c', $vector->get(2));
        self::assertSame(3, $vector->length());
    }

    public function testUpdateIsImmutableDoesNotMutateTheOriginal(): void
    {
        $original = Vector::of('a', 'b');
        $original->update(0, 'A');

        self::assertSame('a', $original->get(0));
    }

    public function testUpdateOutOfRangeThrows(): void
    {
        $this->expectException(\OutOfRangeException::class);

        Vector::of(1)->update(5, 'x');
    }

    public function testAppendManyElementsAllRemainRetrievableInOrder(): void
    {
        $vector = Vector::empty();
        for ($i = 0; $i < 500; ++$i) {
            $vector = $vector->append($i);
        }

        self::assertSame(500, $vector->length());
        for ($i = 0; $i < 500; ++$i) {
            self::assertSame($i, $vector->get($i));
        }
    }

    public function testToArrayReturnsElementsInOrder(): void
    {
        self::assertSame([1, 2, 3], Vector::of(1, 2, 3)->toArray());
    }

    public function testMapTransformsEachElement(): void
    {
        self::assertSame([10, 20, 30], Vector::of(1, 2, 3)->map(fn (int $v): int => $v * 10)->toArray());
    }

    public function testFilterKeepsOnlyMatchingElements(): void
    {
        self::assertSame([2, 4], Vector::of(1, 2, 3, 4)->filter(fn (int $v): bool => $v % 2 === 0)->toArray());
    }

    public function testFoldCombinesAllElements(): void
    {
        self::assertSame(6, Vector::of(1, 2, 3)->fold(0, fn (int $acc, int $v): int => $acc + $v));
    }

    public function testForEachVisitsEveryElementInOrder(): void
    {
        $visited = [];
        Vector::of(1, 2, 3)->forEach(function (int $v) use (&$visited): void {
            $visited[] = $v;
        });

        self::assertSame([1, 2, 3], $visited);
    }

    public function testCountableReturnsLength(): void
    {
        self::assertCount(3, Vector::of(1, 2, 3));
    }

    public function testIteratorAggregateYieldsElementsInOrder(): void
    {
        $collected = [];
        foreach (Vector::of(1, 2, 3) as $element) {
            $collected[] = $element;
        }

        self::assertSame([1, 2, 3], $collected);
    }

    public function testDistinctKeepsFirstOccurrenceOrder(): void
    {
        self::assertSame([3, 1, 2], Vector::of(3, 1, 3, 2, 1)->distinct()->toArray());
    }

    public function testDistinctByKeepsFirstElementWithKey(): void
    {
        $result = Vector::of(['id' => 1, 'v' => 'a'], ['id' => 1, 'v' => 'b'])
            ->distinctBy(static fn (array $row): int => $row['id']);

        self::assertSame([['id' => 1, 'v' => 'a']], $result->toArray());
    }

    public function testDistinctIsStrictAcrossScalarTypes(): void
    {
        self::assertSame([1, true, '1', 1.0], Vector::of(1, true, '1', 1.0)->distinct()->toArray());
    }

    public function testDistinctOnArraysThrowsHashableContractException(): void
    {
        $this->expectException(HashableContractException::class);

        Vector::of([1], [1])->distinct();
    }

    public function testDistinctByJsonEncodeWorksOnArrays(): void
    {
        self::assertSame([[1], [2]], Vector::of([1], [2], [1])->distinctBy(json_encode(...))->toArray());
    }

    public function testDistinctWithNullElementThrowsHashableContractException(): void
    {
        $this->expectException(HashableContractException::class);

        Vector::of(1, null)->distinct();
    }

    public function testDistinctByHashableKeyDeduplicatesEqualObjects(): void
    {
        $result = Vector::of(['k' => new HashableStub('x'), 'tag' => 'first'], ['k' => new HashableStub('x'), 'tag' => 'second'])
            ->distinctBy(static fn (array $row): HashableStub => $row['k']);

        self::assertSame(1, $result->length());
        self::assertSame('first', $result->get(0)['tag']);
    }

    public function testDistinctOnEmptyAndSingleElement(): void
    {
        self::assertSame([], Vector::empty()->distinct()->toArray());
        self::assertSame([7], Vector::of(7)->distinct()->toArray());
    }

    public function testDistinctLeavesOriginalUnchanged(): void
    {
        $original = Vector::of(1, 1, 2);
        $original->distinct();

        self::assertSame([1, 1, 2], $original->toArray());
    }

    public function testDistinctTreatsZeroAndNegativeZeroAsEqual(): void
    {
        self::assertSame([0.0, 1.0], Vector::of(0.0, -0.0, 1.0)->distinct()->toArray());
    }
}
