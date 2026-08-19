<?php

declare(strict_types=1);

namespace Optio\Tests\Collection;

use Optio\Collection\HashMap;
use Optio\Exception\HashableContractException;
use Optio\Tests\Stub\CollidingHashableStub;
use Optio\Tests\Stub\HashableStub;
use Optio\Tests\Stub\NotHashableStub;
use Optio\Tuple\Tuple2;
use PHPUnit\Framework\TestCase;

final class HashMapTest extends TestCase
{
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
        self::assertSame(0, HashMap::empty()->length());
        self::assertTrue(HashMap::empty()->isEmpty());
    }

    public function testPutThenGetReturnsSomeWithTheValue(): void
    {
        $map = HashMap::empty()->put('a', 1);

        self::assertSame(1, $map->get('a')->getOrElse(null));
    }

    public function testGetOnMissingKeyReturnsNone(): void
    {
        self::assertFalse(HashMap::empty()->get('missing')->isDefined());
    }

    public function testPutIsImmutableDoesNotMutateTheOriginal(): void
    {
        $original = HashMap::empty();
        $original->put('a', 1);

        self::assertSame(0, $original->length());
    }

    public function testPutSameKeyTwiceOverwritesTheValue(): void
    {
        $map = HashMap::empty()->put('a', 1)->put('a', 2);

        self::assertSame(2, $map->get('a')->getOrElse(null));
        self::assertSame(1, $map->length());
    }

    public function testRemoveReturnsMapWithoutTheKey(): void
    {
        $map = HashMap::empty()->put('a', 1)->put('b', 2)->remove('a');

        self::assertFalse($map->get('a')->isDefined());
        self::assertSame(1, $map->length());
    }

    public function testRemoveNonExistentKeyIsNoOp(): void
    {
        $map = HashMap::empty()->put('a', 1)->remove('missing');

        self::assertSame(1, $map->length());
    }

    public function testContainsKeyIsTrueForPresentKey(): void
    {
        self::assertTrue(HashMap::empty()->put('a', 1)->containsKey('a'));
    }

    public function testContainsKeyIsFalseForAbsentKey(): void
    {
        self::assertFalse(HashMap::empty()->containsKey('a'));
    }

    public function testKeysReturnsHashSetOfAllKeys(): void
    {
        $keys = HashMap::empty()->put('a', 1)->put('b', 2)->keys()->toArray();
        sort($keys);

        self::assertSame(['a', 'b'], $keys);
    }

    public function testValuesReturnsListOfAllValues(): void
    {
        $values = HashMap::empty()->put('a', 1)->put('b', 2)->values();
        sort($values);

        self::assertSame([1, 2], $values);
    }

    public function testToArrayReturnsListOfTuple2Entries(): void
    {
        $entries = HashMap::empty()->put('a', 1)->toArray();

        self::assertCount(1, $entries);
        self::assertInstanceOf(Tuple2::class, $entries[0]);
        self::assertSame(['a', 1], $entries[0]->toArray());
    }

    public function testMapTransformsEachEntry(): void
    {
        $map = HashMap::empty()->put('a', 1)->put('b', 2);
        $doubled = $map->map(fn (Tuple2 $entry): Tuple2 => new Tuple2($entry[0], self::valueOf($entry) * 10));

        self::assertSame(10, $doubled->get('a')->getOrElse(null));
        self::assertSame(20, $doubled->get('b')->getOrElse(null));
    }

    public function testFilterKeepsOnlyMatchingEntries(): void
    {
        $map = HashMap::empty()->put('a', 1)->put('b', 2)->put('c', 3);
        $filtered = $map->filter(fn (Tuple2 $entry): bool => self::valueOf($entry) % 2 === 1);

        self::assertSame(2, $filtered->length());
        self::assertTrue($filtered->containsKey('a'));
        self::assertTrue($filtered->containsKey('c'));
        self::assertFalse($filtered->containsKey('b'));
    }

    public function testFoldCombinesAllValues(): void
    {
        $map = HashMap::empty()->put('a', 1)->put('b', 2)->put('c', 3);
        $sum = $map->fold(0, fn (int $acc, Tuple2 $entry): int => $acc + self::valueOf($entry));

        self::assertSame(6, $sum);
    }

    public function testForEachVisitsEveryEntry(): void
    {
        $map = HashMap::empty()->put('a', 1)->put('b', 2);
        $visitedKeys = [];
        $map->forEach(function (Tuple2 $entry) use (&$visitedKeys): void {
            $visitedKeys[] = $entry[0];
        });
        sort($visitedKeys);

        self::assertSame(['a', 'b'], $visitedKeys);
    }

    public function testCountableReturnsLength(): void
    {
        self::assertCount(2, HashMap::empty()->put('a', 1)->put('b', 2));
    }

    public function testIteratorAggregateYieldsTuple2Entries(): void
    {
        $map = HashMap::empty()->put('a', 1);
        $collected = [];
        foreach ($map as $entry) {
            $collected[] = $entry;
        }

        self::assertCount(1, $collected);
        self::assertInstanceOf(Tuple2::class, $collected[0]);
    }

    public function testOfAllBuildsMapFromArrayPairs(): void
    {
        $map = HashMap::ofAll([['a', 1], ['b', 2]]);

        self::assertSame(2, $map->length());
        self::assertSame(1, $map->get('a')->getOrElse(null));
        self::assertSame(2, $map->get('b')->getOrElse(null));
    }

    public function testOfAllBuildsMapFromTuple2Entries(): void
    {
        $map = HashMap::ofAll([new Tuple2('a', 1), new Tuple2('b', 2)]);

        self::assertSame(2, $map->length());
        self::assertSame(1, $map->get('a')->getOrElse(null));
        self::assertSame(2, $map->get('b')->getOrElse(null));
    }

    public function testLengthIsConstantTimeAfterManyPuts(): void
    {
        $map = HashMap::empty();
        for ($i = 0; $i < 5000; ++$i) {
            $map = $map->put("key-{$i}", $i);
        }

        self::assertSame(5000, $map->length());

        // Replacing existing keys must not grow the size.
        for ($i = 0; $i < 5000; ++$i) {
            $map = $map->put("key-{$i}", $i * 2);
        }

        self::assertSame(5000, $map->length());

        for ($i = 0; $i < 2500; ++$i) {
            $map = $map->remove("key-{$i}");
        }

        self::assertSame(2500, $map->length());
    }

    public function testHashableKeyIsTreatedAsSameKeyAcrossEqualInstances(): void
    {
        $map = HashMap::empty()->put(new HashableStub('a'), 1);
        $map = $map->put(new HashableStub('a'), 2);

        self::assertSame(1, $map->length());
        self::assertSame(2, $map->get(new HashableStub('a'))->getOrElse(null));
    }

    public function testHashableKeyCanBeRemovedByAnEqualInstance(): void
    {
        $map = HashMap::empty()->put(new HashableStub('a'), 1)->remove(new HashableStub('a'));

        self::assertFalse($map->containsKey(new HashableStub('a')));
        self::assertSame(0, $map->length());
    }

    public function testCollidingHashableKeysAreBothRecoverable(): void
    {
        $first = new CollidingHashableStub('first');
        $second = new CollidingHashableStub('second');

        $map = HashMap::empty()->put($first, 1)->put($second, 2);

        self::assertSame(2, $map->length());
        self::assertSame(1, $map->get($first)->getOrElse(null));
        self::assertSame(2, $map->get($second)->getOrElse(null));
    }

    public function testPutWithNonHashableObjectKeyThrows(): void
    {
        $this->expectException(HashableContractException::class);

        HashMap::empty()->put(new \stdClass(), 1);
    }

    public function testPutWithNullKeyThrows(): void
    {
        $this->expectException(HashableContractException::class);

        HashMap::empty()->put(null, 1);
    }

    public function testPutWithArrayKeyThrows(): void
    {
        $this->expectException(HashableContractException::class);

        HashMap::empty()->put([], 1);
    }

    public function testEmptyHashedThenPutUsesTheHasher(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $map = HashMap::emptyHashed($hasher)
            ->put(new NotHashableStub('Karol', 33), 'a')
            ->put(new NotHashableStub('Karol', 33), 'b');

        self::assertSame(1, $map->length());
        self::assertSame('b', $map->get(new NotHashableStub('Karol', 33))->getOrElse('missing'));
    }

    public function testGetAndContainsKeyUseTheHasher(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $map = HashMap::emptyHashed($hasher)->put(new NotHashableStub('Karol', 33), 'value');

        self::assertTrue($map->containsKey(new NotHashableStub('Karol', 33)));
        self::assertFalse($map->containsKey(new NotHashableStub('Agata', 24)));
    }

    public function testRemoveUsesTheHasher(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $map = HashMap::emptyHashed($hasher)
            ->put(new NotHashableStub('Karol', 33), 'value')
            ->remove(new NotHashableStub('Karol', 33));

        self::assertSame(0, $map->length());
    }

    public function testFilterPreservesTheHasher(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $map = HashMap::emptyHashed($hasher)
            ->put(new NotHashableStub('Karol', 33), 'a')
            ->put(new NotHashableStub('Agata', 24), 'b')
            ->filter(fn (Tuple2 $entry): bool => $entry[1] === 'a');

        self::assertSame(1, $map->length());

        // If the hasher survived filter(), putting a "duplicate" key (by the
        // custom hash) must overwrite instead of growing the map.
        $map = $map->put(new NotHashableStub('Karol', 33), 'c');
        self::assertSame(1, $map->length());
        self::assertSame('c', $map->get(new NotHashableStub('Karol', 33))->getOrElse('missing'));
    }

    public function testSlidingPreservesTheHasher(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $windows = HashMap::emptyHashed($hasher)
            ->put(new NotHashableStub('Karol', 33), 'a')
            ->put(new NotHashableStub('Agata', 24), 'b')
            ->put(new NotHashableStub('Jedrzej', 13), 'c')
            ->sliding(2, 1);

        self::assertSame(3, $windows->length());

        $firstWindow = $windows->get(0);
        // If the hasher survived sliding(), putting a "duplicate" of a key
        // already in the window must overwrite instead of growing the map.
        $existingKey = $firstWindow->keys()->toArray()[0];
        $withDuplicate = $firstWindow->put(new NotHashableStub($existingKey->name, $existingKey->age), 'z');
        self::assertSame($firstWindow->length(), $withDuplicate->length());
    }

    public function testKeysPreservesTheHasher(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $keys = HashMap::emptyHashed($hasher)
            ->put(new NotHashableStub('Karol', 33), 'a')
            ->keys();

        self::assertTrue($keys->contains(new NotHashableStub('Karol', 33)));

        // If the hasher survived keys(), adding a duplicate must still dedupe.
        $keys = $keys->add(new NotHashableStub('Karol', 33));
        self::assertSame(1, $keys->length());
    }

    public function testMapResetsTheHasherAndThrowsOnUnhashableResult(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $this->expectException(HashableContractException::class);

        HashMap::emptyHashed($hasher)
            ->put(new NotHashableStub('Karol', 33), 'a')
            ->map(function (Tuple2 $entry): Tuple2 {
                /** @var NotHashableStub $key */
                $key = $entry[0];

                return new Tuple2(new NotHashableStub($key->name, $key->age + 1), $entry[1]);
            });
    }

    public function testMapHashedAppliesTheNewHasherToMappedKeys(): void
    {
        $hasher = fn (NotHashableStub $p): string => $p->name.':'.$p->age;

        $map = HashMap::emptyHashed($hasher)
            ->put(new NotHashableStub('Karol', 33), 'a')
            ->mapHashed(
                function (Tuple2 $entry): Tuple2 {
                    /** @var NotHashableStub $key */
                    $key = $entry[0];

                    return new Tuple2(new NotHashableStub($key->name, $key->age + 1), $entry[1]);
                },
                $hasher,
            )
            ->put(new NotHashableStub('Karol', 34), 'b');

        self::assertSame(1, $map->length());
        self::assertSame('b', $map->get(new NotHashableStub('Karol', 34))->getOrElse('missing'));
    }
}
