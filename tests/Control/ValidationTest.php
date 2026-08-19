<?php

declare(strict_types=1);

namespace Optio\Tests\Control;

use Optio\Control\Either\Left;
use Optio\Control\Either\Right;
use Optio\Control\Option\None;
use Optio\Control\Option\Some;
use Optio\Control\Validation;
use Optio\Control\Validation\Invalid;
use Optio\Control\Validation\Valid;
use PHPUnit\Framework\TestCase;

final class ValidationTest extends TestCase
{
    public function testValidFactoryReturnsValid(): void
    {
        self::assertInstanceOf(Valid::class, Validation::valid(1));
    }

    public function testInvalidFactoryReturnsInvalid(): void
    {
        self::assertInstanceOf(Invalid::class, Validation::invalid('error'));
    }

    public function testValidConstructedDirectlyIsValid(): void
    {
        self::assertTrue((new Valid(1))->isValid());
    }

    public function testInvalidConstructedDirectlyIsNotValid(): void
    {
        self::assertFalse((new Invalid(['error']))->isValid());
    }

    public function testMapOnValidAppliesFunction(): void
    {
        $result = Validation::valid(2)->map(fn (int $v): int => $v * 10);

        self::assertSame(20, $result->fold(fn (array $e) => null, fn (int $v) => $v));
    }

    public function testMapOnInvalidIsNoOp(): void
    {
        $result = Validation::invalid('e')->map(fn (int $v): int => $v * 10);

        self::assertInstanceOf(Invalid::class, $result);
    }

    public function testMapErrorOnInvalidAppliesFunction(): void
    {
        $result = Validation::invalid('e')->mapError(fn (string $e): string => strtoupper($e));

        self::assertSame(['E'], $result->fold(fn (array $errors) => $errors, fn () => null));
    }

    public function testMapErrorOnValidIsNoOp(): void
    {
        $result = Validation::valid(2)->mapError(fn (string $e): string => strtoupper($e));

        self::assertInstanceOf(Valid::class, $result);
    }

    public function testFlatMapOnValidChainsValidation(): void
    {
        $result = Validation::valid(2)->flatMap(fn (int $v): Validation => Validation::valid($v * 10));

        self::assertSame(20, $result->fold(fn (array $e) => null, fn (int $v) => $v));
    }

    public function testFlatMapOnValidCanCollapseToInvalid(): void
    {
        $result = Validation::valid(2)->flatMap(fn (int $v): Validation => Validation::invalid('failed'));

        self::assertInstanceOf(Invalid::class, $result);
    }

    public function testFlatMapOnInvalidIsNoOp(): void
    {
        $result = Validation::invalid('e')->flatMap(fn (int $v): Validation => Validation::valid($v));

        self::assertInstanceOf(Invalid::class, $result);
    }

    public function testFoldOnValidCallsOnValid(): void
    {
        $result = Validation::valid(2)->fold(fn (array $e): string => 'invalid', fn (int $v): string => "valid:{$v}");

        self::assertSame('valid:2', $result);
    }

    public function testFoldOnInvalidCallsOnInvalidWithErrorList(): void
    {
        $result = Validation::invalid('boom')->fold(
            fn (array $errors): string => 'invalid:'.implode(',', $errors),
            fn (int $v): string => 'valid',
        );

        self::assertSame('invalid:boom', $result);
    }

    public function testCombineTwoValidAppliesCombiner(): void
    {
        $result = Validation::valid(2)->combine(Validation::valid(3), fn (int $a, int $b): int => $a + $b);

        self::assertInstanceOf(Valid::class, $result);
        self::assertSame(5, $result->fold(fn (array $e) => null, fn (mixed $v) => $v));
    }

    public function testCombineValidWithInvalidReturnsTheInvalidSide(): void
    {
        $result = Validation::valid(2)->combine(Validation::invalid('right failed'), fn (int $a, int $b): int => $a + $b);

        self::assertInstanceOf(Invalid::class, $result);
        self::assertSame(['right failed'], $result->fold(fn (array $errors) => $errors, fn () => null));
    }

    public function testCombineInvalidWithValidReturnsTheInvalidSide(): void
    {
        $result = Validation::invalid('left failed')->combine(Validation::valid(3), fn (int $a, int $b): int => $a + $b);

        self::assertInstanceOf(Invalid::class, $result);
        self::assertSame(['left failed'], $result->fold(fn (array $errors) => $errors, fn () => null));
    }

    public function testCombineTwoInvalidAccumulatesBothErrorLists(): void
    {
        $result = Validation::invalid('first failed')->combine(Validation::invalid('second failed'), fn ($a, $b) => null);

        self::assertInstanceOf(Invalid::class, $result);
        self::assertSame(['first failed', 'second failed'], $result->fold(fn (array $errors) => $errors, fn () => null));
    }

    public function testValidToEitherReturnsRight(): void
    {
        self::assertInstanceOf(Right::class, Validation::valid(2)->toEither());
    }

    public function testInvalidToEitherReturnsLeft(): void
    {
        $either = Validation::invalid('e')->toEither();

        self::assertInstanceOf(Left::class, $either);
        self::assertSame(['e'], $either->fold(fn (mixed $errors) => $errors, fn () => null));
    }

    public function testValidToOptionReturnsSome(): void
    {
        self::assertInstanceOf(Some::class, Validation::valid(2)->toOption());
    }

    public function testInvalidToOptionReturnsNone(): void
    {
        self::assertInstanceOf(None::class, Validation::invalid('e')->toOption());
    }
}
