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

    public function testRecoverFromMatchingExceptionGivesSuccess(): void
    {
        $result = (new Failure(new \JsonException('bad')))
            ->recoverFrom(\JsonException::class, static fn (\JsonException $e): array => []);

        self::assertInstanceOf(Success::class, $result);
        self::assertSame([], $result->get());
    }

    public function testRecoverFromNonMatchingExceptionReturnsSameFailureWithoutCallingRecovery(): void
    {
        $failure = new Failure(new \RuntimeException('boom'));

        $result = $failure->recoverFrom(\JsonException::class, static fn (): array => throw new \LogicException('must not be called'));

        self::assertSame($failure, $result);
    }

    public function testRecoverFromMatchesSubclass(): void
    {
        $result = (new Failure(new \UnexpectedValueException()))
            ->recoverFrom(\RuntimeException::class, static fn (): int => 1);

        self::assertSame(1, $result->get());
    }

    public function testRecoverFromMatchesInterface(): void
    {
        $result = (new Failure(new \JsonException()))
            ->recoverFrom(\Throwable::class, static fn (): int => 1);

        self::assertSame(1, $result->get());
    }

    public function testRecoverFromOnSuccessReturnsSameSuccessWithoutCallingRecovery(): void
    {
        $success = new Success(5);

        $result = $success->recoverFrom(\Throwable::class, static fn (): int => throw new \LogicException('must not be called'));

        self::assertSame($success, $result);
    }

    public function testRecoverFromThrowingRecoveryGivesFailureWithNewException(): void
    {
        $thrown = new \LogicException('from recovery');

        $result = (new Failure(new \JsonException()))
            ->recoverFrom(\JsonException::class, static fn (): int => throw $thrown);

        self::assertFalse($result->isSuccess());
        self::assertSame($thrown, $result->fold(static fn (\Throwable $e): \Throwable => $e, static fn (): ?\Throwable => null));
    }

    public function testChainedRecoverFromUsesFirstMatchingClause(): void
    {
        $result = (new Failure(new \JsonException()))
            ->recoverFrom(\LogicException::class, static fn (): string => 'logic')
            ->recoverFrom(\JsonException::class, static fn (): string => 'json');

        self::assertSame('json', $result->get());
    }

    public function testGetOnSuccessReturnsValue(): void
    {
        self::assertSame(5, (new Success(5))->get());
    }

    public function testGetOnSuccessNullReturnsNull(): void
    {
        self::assertNull((new Success(self::opaque(null)))->get());
    }

    /**
     * Hides the argument's type from PHPStan so a null-returning get() isn't flagged as always-true.
     */
    private static function opaque(mixed $value): mixed
    {
        return $value;
    }

    public function testGetOnFailureRethrowsSameExceptionObject(): void
    {
        $exception = new \RuntimeException('boom');

        $this->expectException(\RuntimeException::class);

        try {
            (new Failure($exception))->get();
        } catch (\RuntimeException $caught) {
            self::assertSame($exception, $caught);

            throw $caught;
        }
    }
}
