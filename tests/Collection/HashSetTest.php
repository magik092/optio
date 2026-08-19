<?php

declare(strict_types=1);

namespace Optio\Tests\Collection;

use Optio\Collection\HashSet;
use Optio\Exception\HashableContractException;
use Optio\Tests\Stub\CollidingHashableStub;
use Optio\Tests\Stub\HashableStub;
use Optio\Tests\Stub\NotHashableStub;
use PHPUnit\Framework\TestCase;

final class HashSetTest extends TestCase
{
    public function testEmptyHasLengthZero(): void
    {
        self::assertSame(0, HashSet::empty()->length());
        self::assertTrue(HashSet::empty()->isEmpty());
    }

    public function testOfCreatesSetFromVariadicArguments(): void
    {
        $set = HashSet::of(1, 2, 3);

        self::assertSame(3, $set->length());
    }

    public function testOfAllCreatesSetFromIterable(): void
    {
        $set = HashSet::ofAll([1, 2, 3]);

        self::assertSame(3, $set->length());
    }

    public function testOfAllDeduplicatesEqualElements(): void
    {
        $set = HashSet::ofAll([1, 2, 2, 3, 1]);

        self::assertSame(3, $set->length());
    }

    public function testAddReturnsNewSetContainingTheElement(): void
    {
        $set = HashSet::empty()->add(1);

        self::assertTrue($set->contains(1));
        self::assertSame(1, $set->length());
    }

    public function testAddIsImmutableDoesNotMutateTheOriginal(): void
    {
        $original = HashSet::empty();
        $original->add(1);

        self::assertSame(0, $original->length());
    }

    public function testAddSameElementTwiceDoesNotGrow(): void
    {
        $set = HashSet::empty()->add(1)->add(1);

        self::assertSame(1, $set->length());
    }

    public function testRemoveReturnsSetWithoutTheElement(): void
    {
        $set = HashSet::of(1, 2, 3)->remove(2);

        self::assertFalse($set->contains(2));
        self::assertSame(2, $set->length());
    }

    public function testRemoveNonExistentElementIsNoOp(): void
    {
        $set = HashSet::of(1, 2)->remove(99);

        self::assertSame(2, $set->length());
    }

    public function testContainsIsFalseForAbsentElement(): void
    {
        self::assertFalse(HashSet::of(1, 2)->contains(3));
    }

    public function testToArrayContainsAllElements(): void
    {
        $array = HashSet::of(1, 2, 3)->toArray();
        sort($array);

        self::assertSame([1, 2, 3], $array);
    }

    public function testMapTransformsEachElement(): void
    {
        $array = HashSet::of(1, 2, 3)->map(fn (int $v): int => $v * 10)->toArray();
        sort($array);

        self::assertSame([10, 20, 30], $array);
    }

    public function testFilterKeepsOnlyMatchingElements(): void
    {
        $array = HashSet::of(1, 2, 3, 4)->filter(fn (int $v): bool => $v % 2 === 0)->toArray();
        sort($array);

        self::assertSame([2, 4], $array);
    }

    public function testFoldCombinesAllElements(): void
    {
        $sum = HashSet::of(1, 2, 3)->fold(0, fn (int $acc, int $v): int => $acc + $v);

        self::assertSame(6, $sum);
    }

    public function testForEachVisitsEveryElement(): void
    {
        $visited = [];
        HashSet::of(1, 2, 3)->forEach(function (int $v) use (&$visited): void {
            $visited[] = $v;
        });
        sort($visited);

        self::assertSame([1, 2, 3], $visited);
    }

    public function testCountableReturnsLength(): void
    {
        self::assertCount(3, HashSet::of(1, 2, 3));
    }

    public function testIteratorAggregateYieldsAllElements(): void
    {
        $collected = [];
        foreach (HashSet::of(1, 2, 3) as $element) {
            $collected[] = $element;
        }
        sort($collected);

        self::assertSame([1, 2, 3], $collected);
    }

    public function testLengthIsConstantTimeAfterManyAdds(): void
    {
        $set = HashSet::empty();
        for ($i = 0; $i < 5000; ++$i) {
            $set = $set->add($i);
        }

        self::assertSame(5000, $set->length());

        // Adding the same elements again must not grow the size.
        for ($i = 0; $i < 5000; ++$i) {
            $set = $set->add($i);
        }

        self::assertSame(5000, $set->length());

        for ($i = 0; $i < 2500; ++$i) {
            $set = $set->remove($i);
        }

        self::assertSame(2500, $set->length());
    }

    public function testHashableElementIsTreatedAsSameElementAcrossEqualInstances(): void
    {
        $set = HashSet::empty()->add(new HashableStub('a'))->add(new HashableStub('a'));

        self::assertSame(1, $set->length());
        self::assertTrue($set->contains(new HashableStub('a')));
    }

    public function testHashableElementCanBeRemovedByAnEqualInstance(): void
    {
        $set = HashSet::empty()->add(new HashableStub('a'))->remove(new HashableStub('a'));

        self::assertFalse($set->contains(new HashableStub('a')));
        self::assertSame(0, $set->length());
    }

    public function testCollidingHashableElementsAreBothRecoverable(): void
    {
        $first = new CollidingHashableStub('first');
        $second = new CollidingHashableStub('second');

        $set = HashSet::empty()->add($first)->add($second);

        self::assertSame(2, $set->length());
        self::assertTrue($set->contains($first));
        self::assertTrue($set->contains($second));
    }

    public function testAddWithNonHashableObjectElementThrows(): void
    {
        $this->expectException(HashableContractException::class);

        HashSet::empty()->add(new \stdClass());
    }

    public function testAddWithNullElementThrows(): void
    {
        $this->expectException(HashableContractException::class);

        HashSet::empty()->add(null);
    }

    public function testAddWithArrayElementThrows(): void
    {
        $this->expectException(HashableContractException::class);

        HashSet::empty()->add([]);
    }

    public function testOfHashedDeduplicatesPlainObjectsByCustomHash(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $set = HashSet::ofHashed(
            $hasher,
            new NotHashableStub('Karol', 33),
            new NotHashableStub('Karol', 33),
            new NotHashableStub('Agata', 24),
        );

        self::assertSame(2, $set->length());
    }

    public function testOfAllHashedDeduplicatesPlainObjectsByCustomHash(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $set = HashSet::ofAllHashed($hasher, [
            new NotHashableStub('Karol', 33),
            new NotHashableStub('Karol', 33),
        ]);

        self::assertSame(1, $set->length());
    }

    public function testEmptyHashedThenAddUsesTheHasher(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $set = HashSet::emptyHashed($hasher)
            ->add(new NotHashableStub('Karol', 33))
            ->add(new NotHashableStub('Karol', 33));

        self::assertSame(1, $set->length());
    }

    public function testContainsUsesTheHasher(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $set = HashSet::ofHashed($hasher, new NotHashableStub('Karol', 33));

        self::assertTrue($set->contains(new NotHashableStub('Karol', 33)));
        self::assertFalse($set->contains(new NotHashableStub('Agata', 24)));
    }

    public function testRemoveUsesTheHasher(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $set = HashSet::ofHashed($hasher, new NotHashableStub('Karol', 33))
            ->remove(new NotHashableStub('Karol', 33));

        self::assertSame(0, $set->length());
    }

    public function testFilterPreservesTheHasher(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $set = HashSet::ofHashed(
            $hasher,
            new NotHashableStub('Karol', 33),
            new NotHashableStub('Agata', 24),
        )->filter(fn (NotHashableStub $p): bool => $p->age >= 30);

        self::assertSame(1, $set->length());

        // If the hasher survived filter(), adding a duplicate (by the custom
        // hash) must still dedupe instead of growing the set.
        $set = $set->add(new NotHashableStub('Karol', 33));
        self::assertSame(1, $set->length());
    }

    public function testMapResetsTheHasherAndThrowsOnUnhashableResult(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $this->expectException(HashableContractException::class);

        HashSet::ofHashed($hasher, new NotHashableStub('Karol', 33))
            ->map(fn (NotHashableStub $p): NotHashableStub => new NotHashableStub($p->name, $p->age + 1));
    }

    public function testMapHashedAppliesTheNewHasherToMappedElements(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $set = HashSet::ofHashed($hasher, new NotHashableStub('Karol', 33))
            ->mapHashed(
                fn (NotHashableStub $p): NotHashableStub => new NotHashableStub($p->name, $p->age + 1),
                $hasher,
            )
            ->add(new NotHashableStub('Karol', 34));

        self::assertSame(1, $set->length());
    }
}
