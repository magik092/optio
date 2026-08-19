<?php

declare(strict_types=1);

namespace Optio\Tests\Control;

use Optio\Control\Option;
use Optio\Control\TryTo\Failure;
use Optio\Control\TryTo\Success;
use Optio\Exception\NoSuchElementException;
use PHPUnit\Framework\TestCase;

final class OptionTryToConversionTest extends TestCase
{
    public function testSomeToTryToReturnsSuccess(): void
    {
        $tryTo = Option::some(2)->toTryTo();

        self::assertInstanceOf(Success::class, $tryTo);
        self::assertSame(2, $tryTo->fold(fn () => null, fn (int $v) => $v));
    }

    public function testNoneToTryToReturnsFailureWithNoSuchElementException(): void
    {
        $tryTo = Option::none()->toTryTo();

        self::assertInstanceOf(Failure::class, $tryTo);
        $exception = $tryTo->fold(fn (\Throwable $e) => $e, fn () => null);
        self::assertInstanceOf(NoSuchElementException::class, $exception);
    }
}
