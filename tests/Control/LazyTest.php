<?php

declare(strict_types=1);

namespace Optio\Tests\Control;

use Optio\Control\Lazy;
use PHPUnit\Framework\TestCase;

final class LazyTest extends TestCase
{
    public function testOfDoesNotEvaluateImmediately(): void
    {
        $called = false;

        Lazy::of(function () use (&$called): int {
            $called = true;

            return 1;
        });

        self::assertFalse($called);
    }

    public function testIsEvaluatedIsFalseBeforeGet(): void
    {
        $lazy = Lazy::of(fn (): int => 1);

        self::assertFalse($lazy->isEvaluated());
    }

    public function testGetEvaluatesSupplier(): void
    {
        $lazy = Lazy::of(fn (): int => 42);

        self::assertSame(42, $lazy->get());
    }

    public function testIsEvaluatedIsTrueAfterGet(): void
    {
        $lazy = Lazy::of(fn (): int => 1);
        $lazy->get();

        self::assertTrue($lazy->isEvaluated());
    }

    public function testGetMemoizesSupplierResult(): void
    {
        $calls = 0;
        $lazy = Lazy::of(function () use (&$calls): int {
            ++$calls;

            return $calls;
        });

        $first = $lazy->get();
        $second = $lazy->get();

        self::assertSame(1, $first);
        self::assertSame(1, $second);
        self::assertSame(1, $calls);
    }

    public function testMapDoesNotEvaluateEitherSideImmediately(): void
    {
        $calledOriginal = false;
        $calledMapper = false;

        $lazy = Lazy::of(function () use (&$calledOriginal): int {
            $calledOriginal = true;

            return 1;
        });

        $lazy->map(function (int $v) use (&$calledMapper): int {
            $calledMapper = true;

            return $v * 10;
        });

        self::assertFalse($calledOriginal);
        self::assertFalse($calledMapper);
    }

    public function testMapAppliesFunctionWhenGetIsCalled(): void
    {
        $lazy = Lazy::of(fn (): int => 2)->map(fn (int $v): int => $v * 10);

        self::assertSame(20, $lazy->get());
    }

    public function testSupplierIsReleasedAfterEvaluation(): void
    {
        $lazy = Lazy::of(fn (): int => 1);
        $lazy->get();

        $reflection = new \ReflectionProperty($lazy, 'supplier');
        $reflection->setAccessible(true);

        self::assertNull($reflection->getValue($lazy));
    }
}
