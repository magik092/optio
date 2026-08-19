<?php

declare(strict_types=1);

namespace Optio\Tests\Collection;

use Optio\Collection\LinkedList;
use Optio\Collection\Vector;
use PHPUnit\Framework\TestCase;

final class LargeCollectionTest extends TestCase
{
    private const LARGE_SIZE = 50_000;

    public function testLinkedListToArrayDoesNotOverflowTheStackOnALargeList(): void
    {
        $list = LinkedList::ofAll(range(1, self::LARGE_SIZE));

        self::assertSame(self::LARGE_SIZE, $list->length());
        self::assertSame(1, $list->toArray()[0]);
        self::assertSame(self::LARGE_SIZE, $list->toArray()[self::LARGE_SIZE - 1]);
    }

    public function testLinkedListMapDoesNotOverflowTheStackOnALargeList(): void
    {
        $list = LinkedList::ofAll(range(1, self::LARGE_SIZE))->map(fn (int $v): int => $v * 2);

        self::assertSame(self::LARGE_SIZE, $list->length());
        self::assertSame(2, $list->head());
    }

    public function testLinkedListFoldDoesNotOverflowTheStackOnALargeList(): void
    {
        $list = LinkedList::ofAll(range(1, self::LARGE_SIZE));

        $sum = $list->fold(0, fn (int $acc, int $v): int => $acc + $v);

        self::assertSame(self::LARGE_SIZE * (self::LARGE_SIZE + 1) / 2, $sum);
    }

    public function testLinkedListReverseDoesNotOverflowTheStackOnALargeList(): void
    {
        $list = LinkedList::ofAll(range(1, self::LARGE_SIZE))->reverse();

        self::assertSame(self::LARGE_SIZE, $list->head());
    }

    public function testVectorAppendManyElementsAllRemainRetrievable(): void
    {
        $vector = Vector::empty();
        for ($i = 0; $i < self::LARGE_SIZE; ++$i) {
            $vector = $vector->append($i);
        }

        self::assertSame(self::LARGE_SIZE, $vector->length());
        self::assertSame(0, $vector->get(0));
        self::assertSame(self::LARGE_SIZE - 1, $vector->get(self::LARGE_SIZE - 1));
        self::assertSame(self::LARGE_SIZE / 2, $vector->get(self::LARGE_SIZE / 2));
    }
}
