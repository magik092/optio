<?php

declare(strict_types=1);

namespace Optio\Tests\Control;

use Optio\Collection\Vector;
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
        $result = Option::some(2)->filter(fn (int $v): bool => true);

        self::assertInstanceOf(Some::class, $result);
    }

    public function testFilterTurnsSomeIntoNoneWhenPredicateFalse(): void
    {
        $result = Option::some(2)->filter(fn (int $v): bool => false);

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

        self::assertSame($option, $option->filter(fn (int $v): bool => true));
    }

    public function testToArrayOnNoneIsEmpty(): void
    {
        self::assertSame([], Option::none()->toArray());
    }

    public function testToArrayOnSomeIsSingletonList(): void
    {
        self::assertSame([5], Option::some(5)->toArray());
    }

    public function testToArrayOnSomeNullKeepsNull(): void
    {
        self::assertSame([null], Option::some(null)->toArray());
    }

    public function testSpreadSkipsNoneAndKeepsSomeInOrder(): void
    {
        self::assertSame([1, 2], [...Option::some(1), ...Option::none(), ...Option::some(2)]);
    }

    public function testVectorOfAllAcceptsOption(): void
    {
        self::assertSame(['a'], Vector::ofAll(Option::some('a'))->toArray());
    }

    public function testForeachOverNoneDoesNotRunBody(): void
    {
        $runs = 0;
        foreach (Option::none() as $_) {
            ++$runs;
        }

        self::assertSame(0, $runs);
    }

    public function testForeachOverSomeFalsyValueRunsBodyOnceWithThatValue(): void
    {
        $seen = [];
        foreach (Option::some(false) as $value) {
            $seen[] = $value;
        }

        self::assertSame([false], $seen);
    }

    public function testWhenTrueReturnsSomeOfSupplierResult(): void
    {
        $result = Option::when(true, static fn (): int => 5);

        self::assertInstanceOf(Some::class, $result);
        self::assertSame(5, $result->getOrElse(0));
    }

    public function testWhenFalseReturnsNone(): void
    {
        self::assertInstanceOf(None::class, Option::when(false, static fn (): int => 5));
    }

    public function testWhenFalseDoesNotCallSupplier(): void
    {
        $result = Option::when(false, static fn (): int => throw new \LogicException('must not be called'));

        self::assertInstanceOf(None::class, $result);
    }

    public function testWhenTrueWithNullSupplierResultIsSomeNull(): void
    {
        $result = Option::when(true, static fn (): mixed => null);

        self::assertTrue($result->isDefined());
        self::assertSame([null], $result->toArray());
    }

    public function testWhenTrueSupplierExceptionPropagates(): void
    {
        $this->expectException(\RuntimeException::class);

        Option::when(true, static fn (): int => throw new \RuntimeException('boom'));
    }
}
