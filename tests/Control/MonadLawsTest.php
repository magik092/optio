<?php

declare(strict_types=1);

namespace Optio\Tests\Control;

use Optio\Control\Either;
use Optio\Control\Option;
use Optio\Control\TryTo;
use Optio\Control\TryTo\Success;
use Optio\Control\Validation;
use PHPUnit\Framework\TestCase;

final class MonadLawsTest extends TestCase
{
    /**
     * @param Option<int> $option
     *
     * @return array{0: string, 1: mixed}
     */
    private function optionShape(Option $option): array
    {
        return $option->fold(
            fn (): array => ['none', null],
            fn (mixed $v): array => ['some', $v],
        );
    }

    /**
     * @param Either<string|never, int> $either
     *
     * @return array{0: string, 1: mixed}
     */
    private function eitherShape(Either $either): array
    {
        return $either->fold(
            fn (mixed $l): array => ['left', $l],
            fn (mixed $r): array => ['right', $r],
        );
    }

    /**
     * @param TryTo<int> $tryTo
     *
     * @return array{0: string, 1: mixed}
     */
    private function tryToShape(TryTo $tryTo): array
    {
        return $tryTo->fold(
            fn (\Throwable $e): array => ['failure', $e->getMessage()],
            fn (mixed $v): array => ['success', $v],
        );
    }

    /**
     * @param Validation<string, int> $validation
     *
     * @return array{0: string, 1: mixed}
     */
    private function validationShape(Validation $validation): array
    {
        return $validation->fold(
            fn (array $errors): array => ['invalid', $errors],
            fn (mixed $v): array => ['valid', $v],
        );
    }

    public function testOptionLeftIdentity(): void
    {
        $f = fn (int $x): Option => Option::some($x * 2);

        $left = $f(5);
        $right = Option::some(5)->flatMap($f);

        self::assertSame(
            $this->optionShape($left),
            $this->optionShape($right),
        );
    }

    public function testOptionRightIdentity(): void
    {
        $m = Option::some(5);

        self::assertSame(
            $this->optionShape($m),
            $this->optionShape($m->flatMap(fn (int $x): Option => Option::some($x))),
        );
    }

    public function testOptionAssociativity(): void
    {
        $m = Option::some(5);
        $f = fn (int $x): Option => Option::some($x * 2);
        $g = fn (int $x): Option => Option::some($x + 1);

        $left = $m->flatMap($f)->flatMap($g);
        $right = $m->flatMap(fn (int $x) => $f($x)->flatMap($g));

        self::assertSame($this->optionShape($left), $this->optionShape($right));
    }

    public function testOptionAssociativityWhenChainCollapsesToNone(): void
    {
        $m = Option::some(5);
        $f = fn (int $x): Option => Option::none();
        $g = fn (int $x): Option => Option::some($x + 1);

        $left = $m->flatMap($f)->flatMap($g);
        $right = $m->flatMap(fn (int $x) => $f($x)->flatMap($g));

        self::assertSame($this->optionShape($left), $this->optionShape($right));
    }

    public function testEitherLeftIdentity(): void
    {
        $f = fn (int $x): Either => Either::right($x * 2);

        $left = $f(5);
        $right = Either::right(5)->flatMap($f);

        self::assertSame(
            $this->eitherShape($left),
            $this->eitherShape($right),
        );
    }

    public function testEitherRightIdentity(): void
    {
        $m = Either::right(5);

        self::assertSame(
            $this->eitherShape($m),
            $this->eitherShape($m->flatMap(fn (int $x): Either => Either::right($x))),
        );
    }

    public function testEitherAssociativity(): void
    {
        $m = Either::right(5);
        $f = fn (int $x): Either => Either::right($x * 2);
        $g = fn (int $x): Either => Either::right($x + 1);

        $left = $m->flatMap($f)->flatMap($g);
        $right = $m->flatMap(fn (int $x) => $f($x)->flatMap($g));

        self::assertSame($this->eitherShape($left), $this->eitherShape($right));
    }

    public function testEitherAssociativityWhenChainCollapsesToLeft(): void
    {
        $m = Either::right(5);
        $f = fn (int $x): Either => Either::left('failed');
        $g = fn (int $x): Either => Either::right($x + 1);

        $left = $m->flatMap($f)->flatMap($g);
        $right = $m->flatMap(fn (int $x) => $f($x)->flatMap($g));

        self::assertSame($this->eitherShape($left), $this->eitherShape($right));
    }

    public function testTryToLeftIdentity(): void
    {
        $f = fn (int $x): TryTo => new Success($x * 2);

        self::assertSame(
            $this->tryToShape($f(5)),
            $this->tryToShape((new Success(5))->flatMap($f)),
        );
    }

    public function testTryToRightIdentity(): void
    {
        $m = new Success(5);

        self::assertSame(
            $this->tryToShape($m),
            $this->tryToShape($m->flatMap(fn (int $x): TryTo => new Success($x))),
        );
    }

    public function testTryToAssociativity(): void
    {
        $m = new Success(5);
        $f = fn (int $x): TryTo => new Success($x * 2);
        $g = fn (int $x): TryTo => new Success($x + 1);

        $left = $m->flatMap($f)->flatMap($g);
        $right = $m->flatMap(fn (int $x) => $f($x)->flatMap($g));

        self::assertSame($this->tryToShape($left), $this->tryToShape($right));
    }

    public function testValidationLeftIdentity(): void
    {
        $f = fn (int $x): Validation => Validation::valid($x * 2);

        self::assertSame(
            $this->validationShape($f(5)),
            $this->validationShape(Validation::valid(5)->flatMap($f)),
        );
    }

    public function testValidationRightIdentity(): void
    {
        $m = Validation::valid(5);

        self::assertSame(
            $this->validationShape($m),
            $this->validationShape($m->flatMap(fn (int $x): Validation => Validation::valid($x))),
        );
    }

    public function testValidationAssociativity(): void
    {
        $m = Validation::valid(5);
        $f = fn (int $x): Validation => Validation::valid($x * 2);
        $g = fn (int $x): Validation => Validation::valid($x + 1);

        $left = $m->flatMap($f)->flatMap($g);
        $right = $m->flatMap(fn (int $x) => $f($x)->flatMap($g));

        self::assertSame($this->validationShape($left), $this->validationShape($right));
    }
}
