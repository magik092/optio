<?php

declare(strict_types=1);

namespace Optio\Tests\Control;

use Optio\Control\Either\Left;
use Optio\Control\Either\Right;
use Optio\Control\Future;
use Optio\Control\Option\None;
use Optio\Control\Option\Some;
use PHPUnit\Framework\TestCase;

final class FutureTest extends TestCase
{
    public function testOfRunsTheComputationEagerlyAndSucceeds(): void
    {
        $future = Future::of(fn (): int => 1 + 1);

        self::assertTrue($future->isCompleted());
        self::assertSame(2, $future->get());
    }

    public function testOfCatchesAThrownExceptionIntoAFailure(): void
    {
        $future = Future::of(function (): int {
            throw new \RuntimeException('boom');
        });

        self::assertTrue($future->isCompleted());
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('boom');
        $future->get();
    }

    public function testOfCatchesAnErrorNotOnlyExceptions(): void
    {
        $future = Future::of(function (): int {
            throw new \TypeError('type mismatch');
        });

        $this->expectException(\TypeError::class);
        $future->get();
    }

    public function testSuccessfulBuildsAnAlreadyCompletedSuccess(): void
    {
        $future = Future::successful('value');

        self::assertTrue($future->isCompleted());
        self::assertSame('value', $future->get());
    }

    public function testFailedBuildsAnAlreadyCompletedFailure(): void
    {
        $exception = new \RuntimeException('nope');
        $future = Future::failed($exception);

        self::assertTrue($future->isCompleted());
        self::assertSame($exception, $future->toTryTo()->fold(
            fn (\Throwable $e): \Throwable => $e,
            fn (mixed $v): \Throwable => throw new \LogicException('expected Failure'),
        ));
    }

    public function testMapTransformsASuccessfulValue(): void
    {
        $future = Future::successful(2)->map(fn (int $x): int => $x * 10);

        self::assertSame(20, $future->get());
    }

    public function testMapOnAFailurePassesThroughUnchanged(): void
    {
        $exception = new \RuntimeException('fail');
        $future = Future::failed($exception)->map(fn (int $x): int => $x * 10);

        $this->expectExceptionObject($exception);
        $future->get();
    }

    public function testMapCatchesAnExceptionThrownInsideTheMapper(): void
    {
        $future = Future::successful(2)->map(function (int $x): int {
            throw new \RuntimeException('mapper blew up');
        });

        $this->expectExceptionMessage('mapper blew up');
        $future->get();
    }

    public function testFlatMapChainsToAnotherFuture(): void
    {
        $future = Future::successful(2)->flatMap(
            fn (int $x): Future => Future::successful($x + 1),
        );

        self::assertSame(3, $future->get());
    }

    public function testFlatMapOnAFailureNeverCallsTheMapper(): void
    {
        $called = false;
        $exception = new \RuntimeException('fail');

        $future = Future::failed($exception)->flatMap(function (int $x) use (&$called): Future {
            $called = true;

            return Future::successful($x);
        });

        self::assertFalse($called);
        $this->expectExceptionObject($exception);
        $future->get();
    }

    public function testFlatMapPropagatesAFailureReturnedByTheMapper(): void
    {
        $inner = new \RuntimeException('inner failure');

        $future = Future::successful(1)->flatMap(
            fn (int $x): Future => Future::failed($inner),
        );

        $this->expectExceptionObject($inner);
        $future->get();
    }

    public function testFlatMapCatchesAnExceptionThrownInsideTheMapper(): void
    {
        $future = Future::successful(1)->flatMap(function (int $x): Future {
            if ($x >= 0) {
                throw new \RuntimeException('mapper blew up');
            }

            return Future::successful($x);
        });

        $this->expectExceptionMessage('mapper blew up');
        $future->get();
    }

    public function testGetOrElseReturnsTheValueOnSuccess(): void
    {
        self::assertSame(5, Future::successful(5)->getOrElse(0));
    }

    public function testGetOrElseReturnsTheDefaultOnFailure(): void
    {
        self::assertSame(0, Future::failed(new \RuntimeException())->getOrElse(0));
    }

    public function testToOptionIsSomeOnSuccess(): void
    {
        self::assertEquals(new Some(1), Future::successful(1)->toOption());
    }

    public function testToOptionIsNoneOnFailure(): void
    {
        self::assertEquals(new None(), Future::failed(new \RuntimeException())->toOption());
    }

    public function testToEitherIsRightOnSuccess(): void
    {
        self::assertEquals(new Right(1), Future::successful(1)->toEither());
    }

    public function testToEitherIsLeftOnFailure(): void
    {
        $exception = new \RuntimeException('bad');
        self::assertEquals(new Left($exception), Future::failed($exception)->toEither());
    }
}
