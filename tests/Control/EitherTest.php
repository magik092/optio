<?php

declare(strict_types=1);

namespace Optio\Tests\Control;

use Optio\Control\Either;
use Optio\Control\Either\Left;
use Optio\Control\Either\Right;
use PHPUnit\Framework\TestCase;

final class EitherTest extends TestCase
{
    public function testLeftFactoryReturnsLeft(): void
    {
        self::assertInstanceOf(Left::class, Either::left('error'));
    }

    public function testRightFactoryReturnsRight(): void
    {
        self::assertInstanceOf(Right::class, Either::right('value'));
    }

    public function testRightConstructedDirectlyIsRight(): void
    {
        self::assertTrue((new Right('value'))->isRight());
    }

    public function testLeftConstructedDirectlyIsNotRight(): void
    {
        self::assertFalse((new Left('error'))->isRight());
    }

    public function testMapOnRightAppliesFunction(): void
    {
        $result = Either::right(2)->map(fn (int $v): int => $v * 10);

        self::assertInstanceOf(Right::class, $result);
        self::assertSame(20, $result->fold(fn () => null, fn (int $v) => $v));
    }

    public function testMapOnLeftIsNoOp(): void
    {
        $result = Either::left('error')->map(fn (int $v): int => $v * 10);

        self::assertInstanceOf(Left::class, $result);
        self::assertSame('error', $result->fold(fn (string $l) => $l, fn () => null));
    }

    public function testFlatMapOnRightChainsEither(): void
    {
        $result = Either::right(2)->flatMap(fn (int $v): Either => Either::right($v * 10));

        self::assertSame(20, $result->fold(fn () => null, fn (int $v) => $v));
    }

    public function testFlatMapOnRightCanCollapseToLeft(): void
    {
        $result = Either::right(2)->flatMap(fn (int $v): Either => Either::left('failed'));

        self::assertInstanceOf(Left::class, $result);
    }

    public function testFlatMapOnLeftIsNoOp(): void
    {
        $result = Either::left('error')->flatMap(fn (int $v): Either => Either::right($v));

        self::assertInstanceOf(Left::class, $result);
    }

    public function testFoldOnRightCallsOnRight(): void
    {
        $result = Either::right(2)->fold(fn (string $l): string => "left:{$l}", fn (int $r): string => "right:{$r}");

        self::assertSame('right:2', $result);
    }

    public function testFoldOnLeftCallsOnLeft(): void
    {
        $result = Either::left('boom')->fold(fn (string $l): string => "left:{$l}", fn (int $r): string => "right:{$r}");

        self::assertSame('left:boom', $result);
    }

    public function testSwapTurnsRightIntoLeft(): void
    {
        self::assertInstanceOf(Left::class, Either::right('v')->swap());
    }

    public function testSwapTurnsLeftIntoRight(): void
    {
        self::assertInstanceOf(Right::class, Either::left('e')->swap());
    }

    public function testSwapPreservesValue(): void
    {
        $swapped = Either::right('original')->swap();

        self::assertSame('original', $swapped->fold(fn (string $l): string => $l, fn () => null));
    }
}
