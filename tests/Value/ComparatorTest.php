<?php

declare(strict_types=1);

namespace Optio\Tests\Value;

use Optio\Value\Comparable;
use Optio\Value\Comparator;
use PHPUnit\Framework\TestCase;

final class ComparatorTest extends TestCase
{
    public function testScalarsAreComparedByIdentity(): void
    {
        self::assertTrue(Comparator::equals(1, 1));
        self::assertFalse(Comparator::equals(1, '1'));
    }

    public function testObjectsWithoutComparableUseLooseEquality(): void
    {
        $a = new \stdClass();
        $a->value = 1;
        $b = new \stdClass();
        $b->value = 1;

        self::assertTrue(Comparator::equals($a, $b));
    }

    public function testComparableOnLeftSideIsUsed(): void
    {
        $comparable = new class implements Comparable {
            public function equals(mixed $other): bool
            {
                return true;
            }
        };

        self::assertTrue(Comparator::equals($comparable, 'anything'));
    }

    public function testComparableOnRightSideIsUsed(): void
    {
        $comparable = new class implements Comparable {
            public function equals(mixed $other): bool
            {
                return false;
            }
        };

        self::assertFalse(Comparator::equals('anything', $comparable));
    }
}
