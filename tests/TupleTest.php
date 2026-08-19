<?php

declare(strict_types=1);

namespace Optio\Tests;

use Optio\Tuple;
use Optio\Value\Comparator;
use PHPUnit\Framework\TestCase;

final class TupleTest extends TestCase
{
    public function testOfWithNoArgumentsReturnsTuple0(): void
    {
        self::assertSame(0, Tuple::of()->arity());
    }

    public function testOfWithOneArgumentReturnsTuple1(): void
    {
        self::assertSame(1, Tuple::of('a')->arity());
    }

    public function testOfWithTooManyArgumentsThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Tuple::of(1, 2, 3, 4, 5, 6, 7, 8, 9);
    }

    public function testEqualsComparesArity(): void
    {
        self::assertFalse(Tuple::of('a')->equals(Tuple::of()));
    }

    public function testEqualsComparesValues(): void
    {
        self::assertTrue(Tuple::of('a', 1)->equals(Tuple::of('a', 1)));
        self::assertFalse(Tuple::of('a', 1)->equals(Tuple::of('a', 2)));
    }

    public function testArrayAccessRead(): void
    {
        self::assertSame('a', Tuple::of('a', 1)[0]);
    }

    public function testArrayAccessWriteThrows(): void
    {
        $this->expectException(\Optio\Exception\UnsupportedOperationException::class);

        Tuple::of('a')[0] = 'b';
    }

    public function testEqualsWithNullValuesReturnsTrue(): void
    {
        self::assertTrue(Tuple::of(null)->equals(Tuple::of(null)));
    }

    public function testArrayAccessGetOnNullValueReturnsNullWithoutThrowing(): void
    {
        self::assertNull(Tuple::of(null)[0]);
        self::assertNull(Tuple::of(null)->offsetGet(0));
    }

    public function testIssetOnNullValueDelegatesToOffsetExists(): void
    {
        // This documents PHP's own `isset()` behaviour for ArrayAccess: unlike
        // isset() on plain arrays/properties, isset() on an ArrayAccess offset
        // delegates directly to offsetExists() and does NOT additionally check
        // whether offsetGet() returns null. Since our offsetExists() now uses
        // array_key_exists(), a stored `null` value is correctly reported as set.
        self::assertTrue(isset(Tuple::of(null)[0]));
    }

    public function testOffsetExistsUsesArrayKeyExistsSemantics(): void
    {
        self::assertTrue(Tuple::of(null)->offsetExists(0));
        self::assertFalse(Tuple::of(null)->offsetExists(1));
    }

    public function testOffsetGetOutOfRangeThrows(): void
    {
        $this->expectException(\OutOfRangeException::class);

        Tuple::of('a')->offsetGet(5);
    }

    public function testComparatorRecognizesTupleAsComparable(): void
    {
        self::assertTrue(Comparator::equals(Tuple::of(1, 2), Tuple::of(1, 2)));
        self::assertFalse(Comparator::equals(Tuple::of(1, 2), Tuple::of(1, 3)));
    }
}
