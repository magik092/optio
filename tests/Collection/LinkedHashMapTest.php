<?php

declare(strict_types=1);

namespace Optio\Tests\Collection;

use Optio\Collection\LinkedHashMap;
use PHPUnit\Framework\TestCase;

final class LinkedHashMapTest extends TestCase
{
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
}
