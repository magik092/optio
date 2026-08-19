<?php

declare(strict_types=1);

namespace Optio\Tests\Collection\Internal;

use Optio\Collection\Internal\Sliding;
use PHPUnit\Framework\TestCase;

final class SlidingTest extends TestCase
{
    public function testEmptyInputProducesNoWindows(): void
    {
        self::assertSame([], Sliding::windows([], 2, 1));
    }

    public function testOverlappingWindowsWithStepSmallerThanSize(): void
    {
        $windows = Sliding::windows([1, 2, 3, 4, 5], 3, 1);

        self::assertSame([
            [1, 2, 3],
            [2, 3, 4],
            [3, 4, 5],
            [4, 5],
            [5],
        ], $windows);
    }

    public function testNonOverlappingWindowsWithStepEqualToSize(): void
    {
        $windows = Sliding::windows([1, 2, 3, 4, 5, 6], 2, 2);

        self::assertSame([[1, 2], [3, 4], [5, 6]], $windows);
    }

    public function testNonOverlappingWindowsWithRemainder(): void
    {
        $windows = Sliding::windows([1, 2, 3, 4, 5], 2, 2);

        self::assertSame([[1, 2], [3, 4], [5]], $windows);
    }

    public function testStepLargerThanSizeSkipsElements(): void
    {
        $windows = Sliding::windows([1, 2, 3, 4, 5], 2, 4);

        self::assertSame([[1, 2], [5]], $windows);
    }

    public function testSizeZeroThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Sliding::windows([1, 2, 3], 0, 1);
    }

    public function testStepZeroThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Sliding::windows([1, 2, 3], 1, 0);
    }

    public function testNegativeSizeThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Sliding::windows([1, 2, 3], -1, 1);
    }
}
