<?php

declare(strict_types=1);

namespace Optio\Tests\Collection;

use Optio\Collection\LinkedHashMap;
use Optio\Tuple\Tuple2;
use PHPUnit\Framework\TestCase;

final class LinkedHashMapTest extends TestCase
{
    /**
     * @template T1
     * @template T2
     *
     * @param Tuple2<T1, T2> $entry
     *
     * @return T1
     */
    private static function keyOf(Tuple2 $entry): mixed
    {
        return $entry[0];
    }

    /**
     * @template T1
     * @template T2
     *
     * @param Tuple2<T1, T2> $entry
     *
     * @return T2
     */
    private static function valueOf(Tuple2 $entry): mixed
    {
        return $entry[1];
    }

    public function testEmptyHasLengthZero(): void
    {
        self::assertSame(0, LinkedHashMap::empty()->length());
        self::assertTrue(LinkedHashMap::empty()->isEmpty());
    }

    public function testPutThenGetReturnsTheValue(): void
    {
        $map = LinkedHashMap::empty()->put('a', 1);

        self::assertSame(1, $map->get('a')->getOrElse(0));
        self::assertSame(1, $map->length());
    }

    public function testPutOnExistingKeyReplacesTheValueWithoutGrowing(): void
    {
        $map = LinkedHashMap::empty()->put('a', 1)->put('a', 2);

        self::assertSame(2, $map->get('a')->getOrElse(0));
        self::assertSame(1, $map->length());
    }

    public function testContainsKey(): void
    {
        $map = LinkedHashMap::empty()->put('a', 1);

        self::assertTrue($map->containsKey('a'));
        self::assertFalse($map->containsKey('b'));
    }

    public function testGetOnMissingKeyIsNone(): void
    {
        self::assertFalse(LinkedHashMap::empty()->get('missing')->isDefined());
    }

    public function testOfAllBuildsFromArrayPairs(): void
    {
        $map = LinkedHashMap::ofAll([['a', 1], ['b', 2]]);

        self::assertSame(2, $map->length());
        self::assertSame(1, $map->get('a')->getOrElse(0));
        self::assertSame(2, $map->get('b')->getOrElse(0));
    }

    public function testRemoveDeletesTheKey(): void
    {
        $map = LinkedHashMap::empty()->put('a', 1)->put('b', 2)->remove('a');

        self::assertFalse($map->containsKey('a'));
        self::assertSame(1, $map->length());
    }

    public function testRemoveOnMissingKeyIsANoOp(): void
    {
        $map = LinkedHashMap::empty()->put('a', 1);

        self::assertSame($map->length(), $map->remove('missing')->length());
    }

    public function testToArrayPreservesInsertionOrder(): void
    {
        $map = LinkedHashMap::empty()->put('c', 3)->put('a', 1)->put('b', 2);

        $keys = array_map(fn ($entry) => $entry[0], $map->toArray());
        self::assertSame(['c', 'a', 'b'], $keys);
    }

    public function testRemovingAMiddleKeyDoesNotDisturbTheOrderOfTheRest(): void
    {
        $map = LinkedHashMap::empty()->put('a', 1)->put('b', 2)->put('c', 3)->remove('b');

        $keys = array_map(fn ($entry) => $entry[0], $map->toArray());
        self::assertSame(['a', 'c'], $keys);
    }

    public function testReinsertingARemovedKeyLandsAtTheEndNotItsOldPosition(): void
    {
        $map = LinkedHashMap::empty()->put('a', 1)->put('b', 2)->put('c', 3)
            ->remove('a')
            ->put('a', 99);

        $keys = array_map(fn ($entry) => $entry[0], $map->toArray());
        self::assertSame(['b', 'c', 'a'], $keys);
        self::assertSame(99, $map->get('a')->getOrElse(0));
    }

    public function testCompactionKeepsStateCorrectAfterManyRemoves(): void
    {
        $map = LinkedHashMap::empty();
        for ($i = 0; $i < 20; ++$i) {
            $map = $map->put("key{$i}", $i);
        }
        // Remove enough entries that dead positions outnumber live ones,
        // crossing the compaction threshold at least once.
        for ($i = 0; $i < 15; ++$i) {
            $map = $map->remove("key{$i}");
        }

        self::assertSame(5, $map->length());
        $keys = array_map(fn ($entry) => $entry[0], $map->toArray());
        self::assertSame(['key15', 'key16', 'key17', 'key18', 'key19'], $keys);
    }

    public function testMapTransformsEntriesPreservingOrder(): void
    {
        $map = LinkedHashMap::empty()->put('a', 1)->put('b', 2);

        $result = $map->map(fn (Tuple2 $entry): Tuple2 => new Tuple2(self::keyOf($entry), self::valueOf($entry) * 10));

        self::assertSame(['a', 'b'], array_map(fn ($e) => $e[0], $result->toArray()));
        self::assertSame(10, $result->get('a')->getOrElse(0));
        self::assertSame(20, $result->get('b')->getOrElse(0));
    }

    public function testFilterKeepsOrderOfSurvivingEntries(): void
    {
        $map = LinkedHashMap::empty()->put('a', 1)->put('b', 2)->put('c', 3);

        $result = $map->filter(fn (Tuple2 $entry): bool => self::valueOf($entry) % 2 !== 0);

        self::assertSame(['a', 'c'], array_map(fn ($e) => $e[0], $result->toArray()));
    }

    public function testFoldCombinesValuesInInsertionOrder(): void
    {
        $map = LinkedHashMap::empty()->put('a', 1)->put('b', 2)->put('c', 3);

        $result = $map->fold('', fn (string $acc, Tuple2 $entry): string => $acc.self::keyOf($entry));

        self::assertSame('abc', $result);
    }

    public function testForEachVisitsEntriesInInsertionOrder(): void
    {
        $map = LinkedHashMap::empty()->put('a', 1)->put('b', 2);
        $seen = [];

        $map->forEach(function ($entry) use (&$seen): void {
            $seen[] = $entry[0];
        });

        self::assertSame(['a', 'b'], $seen);
    }

    public function testSlidingWindowsFollowInsertionOrder(): void
    {
        $map = LinkedHashMap::empty()->put('a', 1)->put('b', 2)->put('c', 3);

        $windows = $map->sliding(2, 1);

        self::assertSame(3, $windows->length());
        self::assertSame(['a', 'b'], array_map(fn ($e) => $e[0], $windows->get(0)->toArray()));
        self::assertSame(['b', 'c'], array_map(fn ($e) => $e[0], $windows->get(1)->toArray()));
        self::assertSame(['c'], array_map(fn ($e) => $e[0], $windows->get(2)->toArray()));
    }

    public function testGroupedChunksInInsertionOrder(): void
    {
        $map = LinkedHashMap::empty()->put('a', 1)->put('b', 2)->put('c', 3);

        $groups = $map->grouped(2);

        self::assertSame(2, $groups->length());
        self::assertSame(['a', 'b'], array_map(fn ($e) => $e[0], $groups->get(0)->toArray()));
        self::assertSame(['c'], array_map(fn ($e) => $e[0], $groups->get(1)->toArray()));
    }

    public function testValuesReturnsValuesInInsertionOrder(): void
    {
        $map = LinkedHashMap::empty()->put('a', 1)->put('b', 2);

        self::assertSame([1, 2], $map->values());
    }
}
