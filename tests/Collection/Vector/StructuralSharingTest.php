<?php

declare(strict_types=1);

namespace Optio\Tests\Collection\Vector;

use Optio\Collection\Vector;
use PHPUnit\Framework\TestCase;

final class StructuralSharingTest extends TestCase
{
    public function testAppendOnALargeVectorDoesNotMutateThePreviousVersion(): void
    {
        $vector = Vector::empty();
        for ($i = 0; $i < 5000; ++$i) {
            $vector = $vector->append($i);
        }

        $countBefore = $vector->length();
        $updated = $vector->append(999_999);

        self::assertSame($countBefore, $vector->length(), 'the original vector must be unaffected by a later append()');
        self::assertSame($countBefore + 1, $updated->length());
        self::assertSame(999_999, $updated->get($countBefore));
    }

    public function testUpdateDoesNotMutateThePreviousVersion(): void
    {
        $vector = Vector::ofAll(range(0, 999));

        $updated = $vector->update(500, 'changed');

        self::assertSame(500, $vector->get(500));
        self::assertSame('changed', $updated->get(500));
    }

    public function testTwoIndependentAppendsFromTheSameBaseDoNotInterfere(): void
    {
        $base = Vector::ofAll(range(0, 99));

        $branchA = $base->append('a');
        $branchB = $base->append('b');

        self::assertSame('a', $branchA->get(100));
        self::assertSame('b', $branchB->get(100));
        self::assertSame(100, $base->length());
    }
}
