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
}
