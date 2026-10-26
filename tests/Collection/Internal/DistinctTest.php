<?php

declare(strict_types=1);

namespace Optio\Tests\Collection\Internal;

use Optio\Collection\Internal\Distinct;
use PHPUnit\Framework\TestCase;

final class DistinctTest extends TestCase
{
    public function testKeepsFirstOccurrenceInOrder(): void
    {
        self::assertSame([3, 1, 2], Distinct::by([3, 1, 3, 2, 1], static fn (int $x): int => $x));
    }

    public function testCallsKeyExtractorExactlyOncePerElementInOrder(): void
    {
        $calls = [];
        Distinct::by([1, 2, 1], static function (int $x) use (&$calls): int {
            $calls[] = $x;

            return $x;
        });

        self::assertSame([1, 2, 1], $calls);
    }

    public function testEmptyListGivesEmptyList(): void
    {
        self::assertSame([], Distinct::by([], static fn (mixed $x): mixed => $x));
    }
}
