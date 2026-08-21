<?php

declare(strict_types=1);

namespace Optio\Tests\Collection;

use Optio\Collection\LinkedHashSet;
use Optio\Exception\HashableContractException;
use Optio\Tests\Stub\NotHashableStub;
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

    public function testOfHashedDeduplicatesPlainObjectsByCustomHashKeepingFirstPosition(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $set = LinkedHashSet::ofHashed(
            $hasher,
            new NotHashableStub('Karol', 33),
            new NotHashableStub('Agata', 24),
            new NotHashableStub('Karol', 33),
        );

        self::assertSame(2, $set->length());
    }

    public function testHasherSurvivesAddRemoveAndFilter(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $set = LinkedHashSet::ofHashed($hasher, new NotHashableStub('Karol', 33))
            ->remove(new NotHashableStub('Karol', 33))
            ->add(new NotHashableStub('Karol', 33));

        // If the hasher survived remove()+add(), a duplicate add() must still dedupe.
        self::assertSame(1, $set->add(new NotHashableStub('Karol', 33))->length());

        $filtered = LinkedHashSet::ofHashed($hasher, new NotHashableStub('Karol', 33))
            ->filter(fn ($p) => true);
        self::assertSame(1, $filtered->add(new NotHashableStub('Karol', 33))->length());
    }

    public function testSlidingPreservesTheHasher(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $windows = LinkedHashSet::ofHashed(
            $hasher,
            new NotHashableStub('Karol', 33),
            new NotHashableStub('Agata', 24),
            new NotHashableStub('Jedrzej', 13),
        )->sliding(2, 1);

        $firstWindow = $windows->get(0);
        $withDuplicate = $firstWindow->add(new NotHashableStub('Karol', 33));
        self::assertSame($firstWindow->length(), $withDuplicate->length());
    }

    public function testMapResetsTheHasherAndThrowsOnUnhashableResult(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $this->expectException(HashableContractException::class);

        LinkedHashSet::ofHashed($hasher, new NotHashableStub('Karol', 33))
            ->map(fn (NotHashableStub $p): NotHashableStub => new NotHashableStub($p->name, $p->age + 1));
    }

    public function testMapHashedAppliesTheNewHasherToMappedElements(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $set = LinkedHashSet::ofHashed($hasher, new NotHashableStub('Karol', 33))
            ->mapHashed(
                fn (NotHashableStub $p): NotHashableStub => new NotHashableStub($p->name, $p->age + 1),
                $hasher,
            )
            ->add(new NotHashableStub('Karol', 34));

        self::assertSame(1, $set->length());
    }

    public function testMergeUnionsElementsPreservingThisOrderThenAppendingOtherOnlyElements(): void
    {
        $left = LinkedHashSet::of('b', 'a');
        $right = LinkedHashSet::of('a', 'c');

        $merged = $left->merge($right);

        self::assertSame(['b', 'a', 'c'], $merged->toArray());
    }

    public function testMergeUsesTargetHasherEvenWhenOtherHasNoneOrDifferent(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;
        $otherHasher = fn (NotHashableStub $p): string => $p->age.':'.$p->name;

        $left = LinkedHashSet::ofHashed($hasher, new NotHashableStub('Karol', 33));
        $right = LinkedHashSet::ofHashed($otherHasher, new NotHashableStub('Karol', 33));

        $merged = $left->merge($right);

        self::assertSame(1, $merged->length());
    }
}
