<?php

declare(strict_types=1);

namespace Optio\Tests\Control;

use Optio\Control\Future;
use Optio\Control\TryTo;
use Optio\Tuple\Tuple2;
use PHPUnit\Framework\TestCase;

final class FutureCombinatorsTest extends TestCase
{
    public function testOnSuccessRunsOnASuccessfulFuture(): void
    {
        $seen = null;
        $future = Future::successful(1)->onSuccess(function (int $v) use (&$seen): void {
            $seen = $v;
        });

        self::assertSame(1, $seen);
        self::assertSame($future, $future->onSuccess(fn (int $v) => null));
    }

    public function testOnSuccessDoesNotRunOnAFailedFuture(): void
    {
        $called = false;
        Future::failed(new \RuntimeException())->onSuccess(function () use (&$called): void {
            $called = true;
        });

        self::assertFalse($called);
    }

    public function testOnFailureRunsOnAFailedFuture(): void
    {
        $seen = null;
        $exception = new \RuntimeException('boom');
        Future::failed($exception)->onFailure(function (\Throwable $e) use (&$seen): void {
            $seen = $e;
        });

        self::assertSame($exception, $seen);
    }

    public function testOnFailureDoesNotRunOnASuccessfulFuture(): void
    {
        $called = false;
        Future::successful(1)->onFailure(function () use (&$called): void {
            $called = true;
        });

        self::assertFalse($called);
    }

    public function testOnCompleteRunsForBothSuccessAndFailure(): void
    {
        $results = [];
        Future::successful(1)->onComplete(function (TryTo $r) use (&$results): void {
            $results[] = $r->isSuccess();
        });
        Future::failed(new \RuntimeException())->onComplete(function (TryTo $r) use (&$results): void {
            $results[] = $r->isSuccess();
        });

        self::assertSame([true, false], $results);
    }

    public function testAndThenBehavesLikeOnCompleteAndReturnsSelf(): void
    {
        $ran = false;
        $future = Future::successful(1);
        $returned = $future->andThen(function (TryTo $r) use (&$ran): void {
            $ran = true;
        });

        self::assertTrue($ran);
        self::assertSame($future, $returned);
    }

    public function testAnExceptionInsideOnCompleteIsNotCaughtByFuture(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('handler blew up');

        Future::successful(1)->onComplete(function (): void {
            throw new \RuntimeException('handler blew up');
        });
    }

    public function testRecoverTurnsAFailureIntoASuccess(): void
    {
        $future = Future::failed(new \RuntimeException('x'))->recover(
            fn (\Throwable $e): string => 'recovered: '.$e->getMessage(),
        );

        self::assertSame('recovered: x', $future->get());
    }

    public function testRecoverIsANoOpOnASuccess(): void
    {
        $future = Future::successful(1)->recover(fn (\Throwable $e): int => 99);

        self::assertSame(1, $future->get());
    }

    public function testRecoverWithTurnsAFailureIntoWhateverFutureItReturns(): void
    {
        $future = Future::failed(new \RuntimeException('x'))->recoverWith(
            fn (\Throwable $e): Future => Future::successful(42),
        );

        self::assertSame(42, $future->get());
    }

    public function testRecoverWithIsANoOpOnASuccess(): void
    {
        $called = false;
        $future = Future::successful(1)->recoverWith(function (\Throwable $e) use (&$called): Future {
            $called = true;

            return Future::successful(99);
        });

        self::assertFalse($called);
        self::assertSame(1, $future->get());
    }

    public function testRecoverWithCatchesAnExceptionThrownInsideTheRecovery(): void
    {
        $future = Future::failed(new \RuntimeException('original'))->recoverWith(function (\Throwable $e): Future {
            if ($e->getMessage() === 'original') {
                throw new \RuntimeException('recovery blew up');
            }

            return Future::successful(0);
        });

        $this->expectExceptionMessage('recovery blew up');
        $future->get();
    }

    public function testFallbackToUsesTheOtherFutureOnFailure(): void
    {
        $future = Future::failed(new \RuntimeException())->fallbackTo(Future::successful('fallback'));

        self::assertSame('fallback', $future->get());
    }

    public function testFallbackToIsANoOpOnASuccess(): void
    {
        $future = Future::successful('primary')->fallbackTo(Future::successful('fallback'));

        self::assertSame('primary', $future->get());
    }

    public function testZipCombinesTwoSuccessfulFuturesIntoATuple(): void
    {
        $future = Future::successful(1)->zip(Future::successful('a'));

        self::assertEquals(new Tuple2(1, 'a'), $future->get());
    }

    public function testZipPropagatesTheFirstFailure(): void
    {
        $exception = new \RuntimeException('first');
        $future = Future::failed($exception)->zip(Future::successful('a'));

        $this->expectExceptionObject($exception);
        $future->get();
    }

    public function testZipPropagatesTheSecondFailure(): void
    {
        $exception = new \RuntimeException('second');
        $future = Future::successful(1)->zip(Future::failed($exception));

        $this->expectExceptionObject($exception);
        $future->get();
    }

    public function testSequenceCollectsAllSuccessfulValuesInOrder(): void
    {
        $future = Future::sequence([
            Future::successful(1),
            Future::successful(2),
            Future::successful(3),
        ]);

        self::assertSame([1, 2, 3], $future->get());
    }

    public function testSequenceOnAnEmptyIterableIsAnEmptyList(): void
    {
        self::assertSame([], Future::sequence([])->get());
    }

    public function testSequencePropagatesTheFirstFailureEncountered(): void
    {
        $exception = new \RuntimeException('bad element');
        $future = Future::sequence([
            Future::successful(1),
            Future::failed($exception),
            Future::successful(3),
        ]);

        $this->expectExceptionObject($exception);
        $future->get();
    }
}
