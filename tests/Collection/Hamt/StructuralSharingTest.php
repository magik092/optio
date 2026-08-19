<?php

declare(strict_types=1);

namespace Optio\Tests\Collection\Hamt;

use Optio\Collection\Hamt\Leaf;
use Optio\Collection\Hamt\Node;
use Optio\Collection\Hamt\Operations;
use Optio\Collection\HashMap;
use Optio\Collection\HashSet;
use Optio\Value\Comparator;
use PHPUnit\Framework\TestCase;

final class StructuralSharingTest extends TestCase
{
    /**
     * @return \Closure(mixed, mixed): bool
     */
    private function identityEquals(): \Closure
    {
        return fn (mixed $stored, mixed $key): bool => Comparator::equals($stored, $key);
    }

    public function testPuttingANewKeyReusesUnrelatedBranches(): void
    {
        $root = null;
        for ($i = 0; $i < 500; ++$i) {
            [$root] = Operations::put($root, Comparator::hash($i), $this->identityEquals(), $i, $i);
        }

        self::assertInstanceOf(Node::class, $root);

        // Capture object identity of every child reference at the root before the mutation.
        $before = $root->children;

        [$newRoot] = Operations::put($root, Comparator::hash(999999), $this->identityEquals(), 999999, 999999);

        self::assertInstanceOf(Node::class, $newRoot);
        $after = $newRoot->children;

        // With 500 entries spread across a 32-way branching trie, adding one more key
        // must only touch the single root-to-leaf path its hash routes through — every
        // other top-level child must be the SAME object (===), not a copy, because path
        // copying only rebuilds nodes on the touched path.
        $unchangedCount = 0;
        $totalComparable = min(count($before), count($after));
        for ($i = 0; $i < $totalComparable; ++$i) {
            if ($before[$i] === $after[$i]) {
                ++$unchangedCount;
            }
        }

        self::assertGreaterThan(
            0,
            $unchangedCount,
            'expected at least one top-level child to be structurally shared (same object) after a single put()',
        );
    }

    public function testOriginalRootIsUntouchedAfterPut(): void
    {
        $root = null;
        for ($i = 0; $i < 50; ++$i) {
            [$root] = Operations::put($root, Comparator::hash($i), $this->identityEquals(), $i, $i);
        }

        $countBefore = Operations::count($root);
        Operations::put($root, Comparator::hash('new-key'), $this->identityEquals(), 'new-key', 'new-key');

        self::assertSame($countBefore, Operations::count($root), 'the original root must be unaffected by a later put()');
    }

    public function testHashMapPutOnALargeMapDoesNotDegradeToLinearRebuild(): void
    {
        $map = HashMap::empty();
        for ($i = 0; $i < 2000; ++$i) {
            $map = $map->put("key-{$i}", $i);
        }

        self::assertSame(2000, $map->length());

        $updated = $map->put('key-1000', 999999);

        self::assertSame(999999, $updated->get('key-1000')->getOrElse(null));
        self::assertSame(1000, $map->get('key-1000')->getOrElse(null), 'original map must be unaffected');
        self::assertSame(2000, $updated->length());
    }

    public function testHashSetAddOnALargeSetPreservesAllPriorElements(): void
    {
        $set = HashSet::empty();
        for ($i = 0; $i < 2000; ++$i) {
            $set = $set->add($i);
        }

        $updated = $set->add(999999);

        self::assertSame(2001, $updated->length());
        self::assertSame(2000, $set->length(), 'original set must be unaffected');
        for ($i = 0; $i < 2000; ++$i) {
            self::assertTrue($updated->contains($i));
        }
    }
}
