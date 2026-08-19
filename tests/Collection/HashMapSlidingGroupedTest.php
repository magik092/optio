<?php

declare(strict_types=1);

namespace Optio\Tests\Collection;

use Optio\Collection\HashMap;
use Optio\Collection\Vector;
use Optio\Tuple\Tuple2;
use PHPUnit\Framework\TestCase;

final class HashMapSlidingGroupedTest extends TestCase
{
    public function testGroupedProducesCorrectNumberOfChunksAndCoversAllEntries(): void
    {
        $map = HashMap::empty()->put('a', 1)->put('b', 2)->put('c', 3)->put('d', 4)->put('e', 5);

        $windows = $map->grouped(2)->toArray();

        self::assertCount(3, $windows);

        $allEntries = [];
        foreach ($windows as $window) {
            self::assertInstanceOf(HashMap::class, $window);
            foreach ($window->toArray() as $entry) {
                self::assertInstanceOf(Tuple2::class, $entry);
                $key = $entry[0];
                self::assertIsString($key);
                $allEntries[$key] = $entry[1];
            }
        }
        ksort($allEntries);
        self::assertSame(['a' => 1, 'b' => 2, 'c' => 3, 'd' => 4, 'e' => 5], $allEntries);
    }

    public function testSlidingOnEmptyReturnsEmptyVector(): void
    {
        $result = HashMap::empty()->sliding(2, 1);

        self::assertInstanceOf(Vector::class, $result);
        self::assertTrue($result->isEmpty());
    }

    public function testSlidingWithNonPositiveSizeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        HashMap::empty()->put('a', 1)->sliding(0, 1);
    }

    public function testEachWindowPreservesKeyValueAssociation(): void
    {
        $map = HashMap::empty()->put('x', 100)->put('y', 200);

        $windows = $map->grouped(2)->toArray();

        self::assertCount(1, $windows);
        self::assertSame(100, $windows[0]->get('x')->getOrElse(null));
        self::assertSame(200, $windows[0]->get('y')->getOrElse(null));
    }
}
