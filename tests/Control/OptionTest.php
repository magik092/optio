<?php

declare(strict_types=1);

namespace Optio\Tests\Control;

use Optio\Control\Option;
use Optio\Control\Option\None;
use Optio\Control\Option\Some;
use PHPUnit\Framework\TestCase;

final class OptionTest extends TestCase
{
    public function testOfWithValueReturnsSome(): void
    {
        self::assertInstanceOf(Some::class, Option::of('a'));
    }

    public function testOfWithNullReturnsNone(): void
    {
        self::assertInstanceOf(None::class, Option::of(null));
    }

    public function testSomeConstructedDirectlyIsDefined(): void
    {
        self::assertTrue((new Some('a'))->isDefined());
    }

    public function testNoneConstructedDirectlyIsNotDefined(): void
    {
        self::assertFalse((new None())->isDefined());
    }

    public function testMapOnSomeAppliesFunction(): void
    {
        $result = Option::some(2)->map(fn (int $v): int => $v * 10);

        self::assertInstanceOf(Some::class, $result);
        self::assertSame(20, $result->getOrElse(0));
    }

    public function testMapOnNoneStaysNone(): void
    {
        $result = Option::none()->map(fn (int $v): int => $v * 10);

        self::assertInstanceOf(None::class, $result);
    }

    public function testFlatMapOnSomeChainsOption(): void
    {
        $result = Option::some(2)->flatMap(fn (int $v): Option => Option::some($v * 10));

        self::assertSame(20, $result->getOrElse(0));
    }

    public function testFlatMapOnSomeCanCollapseToNone(): void
    {
        $result = Option::some(2)->flatMap(fn (int $v): Option => Option::none());

        self::assertInstanceOf(None::class, $result);
    }

    public function testFlatMapOnNoneStaysNone(): void
    {
        $result = Option::none()->flatMap(fn (int $v): Option => Option::some($v));

        self::assertInstanceOf(None::class, $result);
    }

    public function testFilterKeepsSomeWhenPredicateTrue(): void
    {
        $result = Option::some(2)->filter(fn (int $v): bool => $v > 0);

        self::assertInstanceOf(Some::class, $result);
    }

    public function testFilterTurnsSomeIntoNoneWhenPredicateFalse(): void
    {
        $result = Option::some(2)->filter(fn (int $v): bool => $v < 0);

        self::assertInstanceOf(None::class, $result);
    }

    public function testFilterOnNoneStaysNone(): void
    {
        $result = Option::none()->filter(fn (int $v): bool => true);

        self::assertInstanceOf(None::class, $result);
    }

    public function testGetOrElseOnSomeReturnsValue(): void
    {
        self::assertSame('a', Option::some('a')->getOrElse('default'));
    }

    public function testGetOrElseOnNoneReturnsDefault(): void
    {
        self::assertSame('default', Option::none()->getOrElse('default'));
    }

    public function testFoldOnSomeCallsOnSome(): void
    {
        $result = Option::some(2)->fold(fn (): string => 'none', fn (int $v): string => "some:{$v}");

        self::assertSame('some:2', $result);
    }

    public function testFoldOnNoneCallsOnNone(): void
    {
        $result = Option::none()->fold(fn (): string => 'none', fn (int $v): string => "some:{$v}");

        self::assertSame('none', $result);
    }

    public function testMapOnNoneDoesNotCallMapper(): void
    {
        $called = false;

        $result = Option::none()->map(function (mixed $v) use (&$called): void {
            $called = true;
        });

        self::assertInstanceOf(None::class, $result);
        self::assertFalse($called);
    }

    public function testFilterOnSomeWithTruePredicateReturnsSameInstance(): void
    {
        $option = Option::some(2);

        self::assertSame($option, $option->filter(fn (int $v): bool => $v > 0));
    }
}
