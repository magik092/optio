<?php

declare(strict_types=1);

namespace Optio\Tests\Control;

use Optio\Control\Either\Left;
use Optio\Control\Either\Right;
use Optio\Control\Option\None;
use Optio\Control\Option\Some;
use Optio\Control\TryTo;
use Optio\Control\TryTo\Failure;
use Optio\Control\TryTo\Success;
use PHPUnit\Framework\TestCase;

final class TryToTest extends TestCase
{
    public function testRunWithSuccessfulSupplierReturnsSuccess(): void
    {
        $result = TryTo::run(fn (): int => 42);

        self::assertInstanceOf(Success::class, $result);
        self::assertTrue($result->isSuccess());
    }

    public function testRunWithThrowingSupplierReturnsFailure(): void
    {
        $result = TryTo::run(function (): int {
            throw new \DomainException('boom');
        });

        self::assertInstanceOf(Failure::class, $result);
        self::assertFalse($result->isSuccess());
    }

    public function testSuccessConstructedDirectlyIsSuccess(): void
    {
        self::assertTrue((new Success(1))->isSuccess());
    }

    public function testFailureConstructedDirectlyIsNotSuccess(): void
    {
        self::assertFalse((new Failure(new \RuntimeException('x')))->isSuccess());
    }

    public function testMapOnSuccessAppliesFunction(): void
    {
        $result = (new Success(2))->map(fn (int $v): int => $v * 10);

        self::assertInstanceOf(Success::class, $result);
        self::assertSame(20, $result->fold(fn () => null, fn (int $v) => $v));
    }

    public function testMapOnSuccessCatchesThrownException(): void
    {
        $result = (new Success(2))->map(function (int $v): int {
            throw new \DomainException('mapper failed');
        });

        self::assertInstanceOf(Failure::class, $result);
    }

    public function testMapOnFailureIsNoOp(): void
    {
        $result = (new Failure(new \RuntimeException('x')))->map(fn (int $v): int => $v * 10);

        self::assertInstanceOf(Failure::class, $result);
    }

    public function testFlatMapOnSuccessChainsTryTo(): void
    {
        $result = (new Success(2))->flatMap(fn (int $v): TryTo => new Success($v * 10));

        self::assertSame(20, $result->fold(fn () => null, fn (int $v) => $v));
    }

    public function testFlatMapOnSuccessCatchesThrownException(): void
    {
        $result = (new Success(2))->flatMap(function (int $v): TryTo {
            throw new \DomainException('chained failure');

            return new Success($v); // @phpstan-ignore-line unreachable, but gives PHPStan concrete type to unify
        });

        self::assertInstanceOf(Failure::class, $result);
    }

    public function testFlatMapOnFailureIsNoOp(): void
    {
        $result = (new Failure(new \RuntimeException('x')))->flatMap(fn (int $v): TryTo => new Success($v));

        self::assertInstanceOf(Failure::class, $result);
    }

    public function testRecoverOnFailureReturnsSuccess(): void
    {
        $result = (new Failure(new \RuntimeException('x')))->recover(fn (\Throwable $e): int => 99);

        self::assertInstanceOf(Success::class, $result);
        self::assertSame(99, $result->fold(fn () => null, fn (int $v) => $v));
    }

    public function testRecoverOnSuccessIsNoOp(): void
    {
        $result = (new Success(2))->recover(fn (\Throwable $e): int => 99);

        self::assertInstanceOf(Success::class, $result);
        self::assertSame(2, $result->fold(fn () => null, fn (int $v) => $v));
    }

    public function testFoldOnSuccessCallsOnSuccess(): void
    {
        $result = (new Success(2))->fold(fn (\Throwable $e): string => 'failure', fn (int $v): string => "success:{$v}");

        self::assertSame('success:2', $result);
    }

    public function testFoldOnFailureCallsOnFailure(): void
    {
        $result = (new Failure(new \RuntimeException('boom')))->fold(
            fn (\Throwable $e): string => "failure:{$e->getMessage()}",
            fn (int $v): string => 'success',
        );

        self::assertSame('failure:boom', $result);
    }

    public function testSuccessToOptionReturnsSome(): void
    {
        self::assertInstanceOf(Some::class, (new Success(2))->toOption());
    }

    public function testFailureToOptionReturnsNone(): void
    {
        self::assertInstanceOf(None::class, (new Failure(new \RuntimeException('x')))->toOption());
    }

    public function testSuccessToEitherReturnsRight(): void
    {
        self::assertInstanceOf(Right::class, (new Success(2))->toEither());
    }

    public function testFailureToEitherReturnsLeft(): void
    {
        self::assertInstanceOf(Left::class, (new Failure(new \RuntimeException('x')))->toEither());
    }
}
