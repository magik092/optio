<?php

declare(strict_types=1);

namespace Optio\Tests\Control;

use Optio\Control\Either;
use Optio\Control\Either\Left;
use Optio\Control\Either\Right;
use Optio\Control\Option;
use Optio\Control\Option\None;
use Optio\Control\Option\Some;
use PHPUnit\Framework\TestCase;

final class OptionEitherConversionTest extends TestCase
{
    public function testSomeToEitherReturnsRight(): void
    {
        $either = Option::some(2)->toEither('missing');

        self::assertInstanceOf(Right::class, $either);
        self::assertSame(2, $either->fold(fn () => null, fn (int $v) => $v));
    }

    public function testNoneToEitherReturnsLeftWithGivenValue(): void
    {
        $either = Option::none()->toEither('missing');

        self::assertInstanceOf(Left::class, $either);
        self::assertSame('missing', $either->fold(fn (string $l) => $l, fn () => null));
    }

    public function testRightToOptionReturnsSome(): void
    {
        $option = Either::right(2)->toOption();

        self::assertInstanceOf(Some::class, $option);
        self::assertSame(2, $option->getOrElse(0));
    }

    public function testLeftToOptionReturnsNone(): void
    {
        $option = Either::left('boom')->toOption();

        self::assertInstanceOf(None::class, $option);
    }

    public function testCrossMonadCompositionMirroringSpecExample(): void
    {
        $findUser = function (int $id): Option {
            $fakeDb = [1 => 'Alice', 2 => 'Bob'];

            return isset($fakeDb[$id]) ? Option::some($fakeDb[$id]) : Option::none();
        };

        $result = $findUser(2)
            ->map(fn (string $name): string => strtoupper($name))
            ->toEither('user not found')
            ->fold(
                fn (string $error): string => "Failure: {$error}",
                fn (string $name): string => "Success: {$name}",
            );

        self::assertSame('Success: BOB', $result);

        $missing = $findUser(99)
            ->map(fn (string $name): string => strtoupper($name))
            ->toEither('user not found')
            ->fold(
                fn (string $error): string => "Failure: {$error}",
                fn (string $name): string => "Success: {$name}",
            );

        self::assertSame('Failure: user not found', $missing);
    }
}
