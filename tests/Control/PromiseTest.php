<?php

declare(strict_types=1);

namespace Optio\Tests\Control;

use Optio\Control\Promise;
use PHPUnit\Framework\TestCase;

final class PromiseTest extends TestCase
{
    public function testAFreshPromisesFutureIsNotCompleted(): void
    {
        $promise = Promise::make();

        self::assertFalse($promise->future()->isCompleted());
    }

    public function testSuccessCompletesTheAssociatedFutureWithTheValue(): void
    {
        $promise = Promise::make();
        $future = $promise->future();

        $promise->success(42);

        self::assertTrue($future->isCompleted());
        self::assertSame(42, $future->get());
    }

    public function testFailureCompletesTheAssociatedFutureWithTheException(): void
    {
        $promise = Promise::make();
        $future = $promise->future();
        $exception = new \RuntimeException('failed from outside');

        $promise->failure($exception);

        self::assertTrue($future->isCompleted());
        $this->expectExceptionObject($exception);
        $future->get();
    }

    public function testSuccessCalledTwiceThrows(): void
    {
        $promise = Promise::make();
        $promise->success(1);

        $this->expectException(\LogicException::class);
        $promise->success(2);
    }

    public function testFailureAfterSuccessThrows(): void
    {
        $promise = Promise::make();
        $promise->success(1);

        $this->expectException(\LogicException::class);
        $promise->failure(new \RuntimeException());
    }

    public function testOnCompleteRegisteredBeforeCompletionWaitsThenFires(): void
    {
        $promise = Promise::make();
        $seen = null;

        $promise->future()->onComplete(function ($result) use (&$seen): void {
            $seen = $result->isSuccess();
        });

        self::assertNull($seen, 'listener must not fire before completion');

        $promise->success('value');

        self::assertTrue($seen);
    }

    public function testOnCompleteRegisteredAfterCompletionFiresImmediately(): void
    {
        $promise = Promise::make();
        $promise->success('value');

        $seen = null;
        $promise->future()->onComplete(function ($result) use (&$seen): void {
            $seen = $result->isSuccess();
        });

        self::assertTrue($seen);
    }

    public function testMapChainedOnAPendingFutureResolvesAfterCompletion(): void
    {
        $promise = Promise::make();
        $mapped = $promise->future()->map(function (mixed $x): int {
            if (!\is_int($x)) {
                throw new \LogicException('expected int');
            }

            return $x * 2;
        });

        self::assertFalse($mapped->isCompleted());

        $promise->success(21);

        self::assertTrue($mapped->isCompleted());
        self::assertSame(42, $mapped->get());
    }

    public function testFlatMapChainedOnAPendingFutureResolvesAfterCompletion(): void
    {
        $promise = Promise::make();
        $chained = $promise->future()->flatMap(function (mixed $x): \Optio\Control\Future {
            if (!\is_int($x)) {
                throw new \LogicException('expected int');
            }

            return \Optio\Control\Future::successful($x + 1);
        });

        self::assertFalse($chained->isCompleted());

        $promise->success(1);

        self::assertTrue($chained->isCompleted());
        self::assertSame(2, $chained->get());
    }

    public function testFlatMapChainedOnAPendingFutureCatchesAThrowingMapper(): void
    {
        $promise = Promise::make();
        $chained = $promise->future()->flatMap(function (mixed $x): \Optio\Control\Future {
            if (\is_int($x)) {
                throw new \RuntimeException('deferred mapper blew up');
            }

            return \Optio\Control\Future::successful($x);
        });

        $promise->success(1);

        self::assertTrue($chained->isCompleted());
        $this->expectExceptionMessage('deferred mapper blew up');
        $chained->get();
    }

    public function testMultipleListenersOnTheSamePendingFutureAllFire(): void
    {
        $promise = Promise::make();
        $calls = [];

        $future = $promise->future();
        $future->onComplete(function () use (&$calls): void {
            $calls[] = 'first';
        });
        $future->onComplete(function () use (&$calls): void {
            $calls[] = 'second';
        });

        $promise->success(1);

        self::assertSame(['first', 'second'], $calls);
    }
}
