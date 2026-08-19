<?php

declare(strict_types=1);

namespace Optio\Tests\Value;

use Optio\Control\Option;
use Optio\Control\Option\None;
use Optio\Control\Option\Some;
use Optio\Exception\MatchNotFoundException;
use Optio\Exception\MultipleDefaultCasesException;
use Optio\Value\Matcher;
use PHPUnit\Framework\TestCase;

final class MatcherTest extends TestCase
{
    public function testMatchesTheFirstCaseWhoseClassMatchesTheValue(): void
    {
        $result = Matcher::value(Option::some(42))
            ->case(Some::class, fn (Some $some): string => 'some')
            ->case(None::class, fn (None $none): string => 'none')
            ->get();

        self::assertSame('some', $result);
    }

    public function testMatchesTheSecondCaseWhenTheFirstDoesNotApply(): void
    {
        $result = Matcher::value(Option::none())
            ->case(Some::class, fn (Some $some): string => 'some')
            ->case(None::class, fn (None $none): string => 'none')
            ->get();

        self::assertSame('none', $result);
    }

    public function testCasesAreCheckedInOrderFirstMatchWins(): void
    {
        // \RuntimeException is a superclass of \InvalidArgumentException? No —
        // it is not; use two unrelated-but-both-matching-\Exception cases to
        // prove order matters, with \Exception itself as a catch-all first.
        $result = Matcher::value(new \InvalidArgumentException('x'))
            ->case(\Exception::class, fn (): string => 'generic')
            ->case(\InvalidArgumentException::class, fn (): string => 'specific')
            ->get();

        self::assertSame('generic', $result);
    }

    public function testHandlerReceivesTheMatchedValue(): void
    {
        $result = Matcher::value(Option::some(42))
            ->case(Some::class, fn (Some $some): mixed => $some->getOrElse(0))
            ->get();

        self::assertSame(42, $result);
    }

    public function testThrowsWhenNoCaseMatchesAndNoDefaultIsSet(): void
    {
        $this->expectException(MatchNotFoundException::class);

        Matcher::value('a string')
            ->case(Some::class, fn (Some $some): string => 'some')
            ->get();
    }

    public function testDefaultIsUsedWhenNoCaseMatches(): void
    {
        $result = Matcher::value('a string')
            ->case(Some::class, fn (Some $some): string => 'some')
            ->default(fn (mixed $v): string => 'fallback')
            ->get();

        self::assertSame('fallback', $result);
    }

    public function testDefaultIsNotUsedWhenACaseMatches(): void
    {
        $result = Matcher::value(Option::some(1))
            ->case(Some::class, fn (Some $some): string => 'some')
            ->default(fn (mixed $v): string => 'fallback')
            ->get();

        self::assertSame('some', $result);
    }

    public function testCallingDefaultTwiceThrows(): void
    {
        $this->expectException(MultipleDefaultCasesException::class);

        Matcher::value(1)
            ->default(fn (mixed $v): string => 'first')
            ->default(fn (mixed $v): string => 'second');
    }

    public function testMatchIsImmutableEachCaseReturnsANewInstance(): void
    {
        $base = Matcher::value(Option::some(1));
        $withCase = $base->case(Some::class, fn (Some $some): string => 'some');

        $this->expectException(MatchNotFoundException::class);
        $base->get();
    }
}
