<?php

declare(strict_types=1);

namespace Optio\Tests\Collection\Hamt;

use Optio\Collection\Hamt\Operations;
use Optio\Tests\Stub\CollidingHashableStub;
use Optio\Value\Comparator;
use PHPUnit\Framework\TestCase;

final class OperationsTest extends TestCase
{
    /**
     * @return \Closure(mixed, mixed): bool
     */
    private function identityEquals(): \Closure
    {
        return fn (mixed $stored, mixed $key): bool => Comparator::equals($stored, $key);
    }

    public function testGetOnEmptyRootReturnsNone(): void
    {
        $result = Operations::get(null, Comparator::hash('a'), $this->identityEquals(), 'a');

        self::assertFalse($result->isDefined());
    }

    public function testPutThenGetReturnsTheValue(): void
    {
        [$root] = Operations::put(null, Comparator::hash('a'), $this->identityEquals(), 'a', 'a');

        $result = Operations::get($root, Comparator::hash('a'), $this->identityEquals(), 'a');

        self::assertTrue($result->isDefined());
        self::assertSame('a', $result->getOrElse(null));
    }

    public function testPutReturnsTrueForNewKey(): void
    {
        [, $isNew] = Operations::put(null, Comparator::hash('a'), $this->identityEquals(), 'a', 'a');

        self::assertTrue($isNew);
    }

    public function testPutSameKeyTwiceReturnsFalseForIsNewOnSecondPut(): void
    {
        [$root] = Operations::put(null, Comparator::hash('a'), $this->identityEquals(), 'a', 'a');
        [, $isNew] = Operations::put($root, Comparator::hash('a'), $this->identityEquals(), 'a', 'a');

        self::assertFalse($isNew);
    }

    public function testPutSameKeyTwiceReplacesTheEntry(): void
    {
        $keyEquals = function (mixed $stored, mixed $key): bool {
            return is_array($stored) && $stored[0] === $key;
        };

        [$root] = Operations::put(null, Comparator::hash('a'), $keyEquals, ['a', 1], 'a');
        [$root2] = Operations::put($root, Comparator::hash('a'), $keyEquals, ['a', 2], 'a');

        $result = Operations::get($root2, Comparator::hash('a'), $keyEquals, 'a');

        self::assertSame(['a', 2], $result->getOrElse(null));
    }

    public function testPuttingManyKeysAllRemainRetrievable(): void
    {
        $root = null;
        $keys = [];
        for ($i = 0; $i < 200; ++$i) {
            $key = "key-{$i}";
            $keys[] = $key;
            [$root] = Operations::put($root, Comparator::hash($key), $this->identityEquals(), $key, $key);
        }

        foreach ($keys as $key) {
            $result = Operations::get($root, Comparator::hash($key), $this->identityEquals(), $key);
            self::assertTrue($result->isDefined(), "expected {$key} to be present");
            self::assertSame($key, $result->getOrElse(null));
        }
    }

    public function testRemoveOnEmptyRootReturnsRootUnchangedAndFalse(): void
    {
        [$root, $removed] = Operations::remove(null, Comparator::hash('a'), $this->identityEquals(), 'a');

        self::assertNull($root);
        self::assertFalse($removed);
    }

    public function testRemoveExistingKeyReturnsTrueAndRemovesIt(): void
    {
        [$root] = Operations::put(null, Comparator::hash('a'), $this->identityEquals(), 'a', 'a');
        [$root2, $removed] = Operations::remove($root, Comparator::hash('a'), $this->identityEquals(), 'a');

        self::assertTrue($removed);
        self::assertNull($root2);
        self::assertFalse(Operations::get($root2, Comparator::hash('a'), $this->identityEquals(), 'a')->isDefined());
    }

    public function testRemoveOneOfManyKeysLeavesOthersIntact(): void
    {
        $root = null;
        $keys = [];
        for ($i = 0; $i < 50; ++$i) {
            $key = "key-{$i}";
            $keys[] = $key;
            [$root] = Operations::put($root, Comparator::hash($key), $this->identityEquals(), $key, $key);
        }

        [$root2, $removed] = Operations::remove($root, Comparator::hash('key-25'), $this->identityEquals(), 'key-25');

        self::assertTrue($removed);
        self::assertFalse(Operations::get($root2, Comparator::hash('key-25'), $this->identityEquals(), 'key-25')->isDefined());

        foreach ($keys as $key) {
            if ($key === 'key-25') {
                continue;
            }
            self::assertTrue(Operations::get($root2, Comparator::hash($key), $this->identityEquals(), $key)->isDefined(), "expected {$key} to remain");
        }
    }

    public function testRemoveNonExistentKeyReturnsFalseAndUnchangedRoot(): void
    {
        [$root] = Operations::put(null, Comparator::hash('a'), $this->identityEquals(), 'a', 'a');
        [$root2, $removed] = Operations::remove($root, Comparator::hash('missing'), $this->identityEquals(), 'missing');

        self::assertFalse($removed);
        self::assertSame($root, $root2);
    }

    public function testEachVisitsEveryStoredEntryExactlyOnce(): void
    {
        $root = null;
        for ($i = 0; $i < 40; ++$i) {
            [$root] = Operations::put($root, Comparator::hash($i), $this->identityEquals(), $i, $i);
        }

        $visited = [];
        Operations::each($root, function (mixed $entry) use (&$visited): void {
            $visited[] = $entry;
        });

        sort($visited);
        self::assertSame(range(0, 39), $visited);
    }

    public function testCountOnEmptyRootIsZero(): void
    {
        self::assertSame(0, Operations::count(null));
    }

    public function testCountMatchesNumberOfDistinctKeysInserted(): void
    {
        $root = null;
        for ($i = 0; $i < 40; ++$i) {
            [$root] = Operations::put($root, Comparator::hash($i), $this->identityEquals(), $i, $i);
        }

        self::assertSame(40, Operations::count($root));
    }

    public function testHashCollisionStoresBothEntriesAndBothAreRetrievable(): void
    {
        $a = new CollidingHashableStub('a');
        $b = new CollidingHashableStub('b');

        $keyEquals = function (mixed $stored, mixed $key): bool {
            return $stored instanceof CollidingHashableStub && $key instanceof CollidingHashableStub && $stored->id === $key->id;
        };

        [$root] = Operations::put(null, Comparator::hash($a), $keyEquals, $a, $a);
        [$root2] = Operations::put($root, Comparator::hash($b), $keyEquals, $b, $b);

        self::assertSame(2, Operations::count($root2));
        self::assertTrue(Operations::get($root2, Comparator::hash($a), $keyEquals, $a)->isDefined());
        self::assertTrue(Operations::get($root2, Comparator::hash($b), $keyEquals, $b)->isDefined());
    }

    public function testRemovingOneOfTwoCollidingEntriesKeepsTheOther(): void
    {
        $a = new CollidingHashableStub('a');
        $b = new CollidingHashableStub('b');

        $keyEquals = function (mixed $stored, mixed $key): bool {
            return $stored instanceof CollidingHashableStub && $key instanceof CollidingHashableStub && $stored->id === $key->id;
        };

        [$root] = Operations::put(null, Comparator::hash($a), $keyEquals, $a, $a);
        [$root2] = Operations::put($root, Comparator::hash($b), $keyEquals, $b, $b);
        [$root3, $removed] = Operations::remove($root2, Comparator::hash($a), $keyEquals, $a);

        self::assertTrue($removed);
        self::assertSame(1, Operations::count($root3));
        self::assertFalse(Operations::get($root3, Comparator::hash($a), $keyEquals, $a)->isDefined());
        self::assertTrue(Operations::get($root3, Comparator::hash($b), $keyEquals, $b)->isDefined());
    }

    public function testPutDoesNotMutateThePreviousRoot(): void
    {
        [$root1] = Operations::put(null, Comparator::hash('a'), $this->identityEquals(), 'a', 'a');
        [$root2] = Operations::put($root1, Comparator::hash('b'), $this->identityEquals(), 'b', 'b');

        self::assertFalse(Operations::get($root1, Comparator::hash('b'), $this->identityEquals(), 'b')->isDefined());
        self::assertTrue(Operations::get($root2, Comparator::hash('b'), $this->identityEquals(), 'b')->isDefined());
        self::assertTrue(Operations::get($root1, Comparator::hash('a'), $this->identityEquals(), 'a')->isDefined());
    }
}
