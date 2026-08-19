<?php

declare(strict_types=1);

namespace Optio\Tests\Value;

use Optio\Exception\HashableContractException;
use Optio\Tests\Stub\HashableStub;
use Optio\Value\Comparator;
use PHPUnit\Framework\TestCase;

final class ComparatorHashTest extends TestCase
{
    public function testHashOfIntIsDeterministic(): void
    {
        self::assertSame(Comparator::hash(42), Comparator::hash(42));
    }

    public function testHashOfIntReturnsTheIntItself(): void
    {
        self::assertSame(42, Comparator::hash(42));
    }

    public function testHashOfStringIsDeterministic(): void
    {
        self::assertSame(Comparator::hash('hello'), Comparator::hash('hello'));
    }

    public function testHashOfDifferentStringsDiffer(): void
    {
        self::assertNotSame(Comparator::hash('hello'), Comparator::hash('world'));
    }

    public function testHashOfBoolTrueAndFalseDiffer(): void
    {
        self::assertNotSame(Comparator::hash(true), Comparator::hash(false));
    }

    public function testHashOfFloatIsDeterministic(): void
    {
        self::assertSame(Comparator::hash(3.14), Comparator::hash(3.14));
    }

    public function testHashOfHashableObjectIsDeterministic(): void
    {
        $a = new HashableStub('same-key');
        $b = new HashableStub('same-key');

        self::assertSame(Comparator::hash($a), Comparator::hash($b));
    }

    public function testHashOfHashableObjectsWithDifferentKeysDiffer(): void
    {
        $a = new HashableStub('key-a');
        $b = new HashableStub('key-b');

        self::assertNotSame(Comparator::hash($a), Comparator::hash($b));
    }

    public function testHashOfNonHashableObjectThrows(): void
    {
        $this->expectException(HashableContractException::class);

        Comparator::hash(new \stdClass());
    }

    public function testHashOfArrayThrows(): void
    {
        $this->expectException(HashableContractException::class);

        Comparator::hash([1, 2, 3]);
    }

    public function testHashOfNullThrows(): void
    {
        $this->expectException(HashableContractException::class);

        Comparator::hash(null);
    }
}
