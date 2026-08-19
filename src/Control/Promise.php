<?php

declare(strict_types=1);

namespace Optio\Control;

use Optio\Control\TryTo\Failure;
use Optio\Control\TryTo\Success;

/**
 * A writable handle paired with a Future: success()/failure() complete the
 * associated Future exactly once, from outside code that doesn't itself
 * produce a Future (e.g. a callback-based third-party API). Use
 * Promise::make() to create a pending Promise/Future pair.
 *
 * T is invariant (not covariant) because it occurs in a contravariant
 * position in success()'s parameter, matching Future's own invariance.
 *
 * make() deliberately returns self<mixed> rather than a fresh self<T>: it
 * takes zero arguments, so — unlike Future::pending(), whose witness closure
 * is always handed in by a caller that already has a real
 * \Closure(never): TryTo<U> value to type it with (see chain()/flatMap() in
 * Future.php) — there is nothing at all here to bind a template from. A
 * pending Promise genuinely does not know its value's type until success()
 * or failure() is called, so self<mixed> is an honest description of that
 * state, not a workaround. success()/failure() still enforce nothing extra
 * at runtime; callers that need a precisely-typed Future should narrow the
 * result of future() themselves (e.g. via a typed variable or property).
 *
 * @template T
 */
final class Promise
{
    /**
     * @param Future<T> $future
     */
    private function __construct(private readonly Future $future)
    {
    }

    /**
     * Creates a pending Promise/Future pair.
     *
     * @return self<mixed>
     */
    public static function make(): self
    {
        return new self(Future::pending(self::witness()));
    }

    /**
     * Never invoked; exists purely to give Future::pending() a fully-typed
     * \Closure(never): TryTo<mixed> witness (see the class docblock for why
     * make() cannot bind a fresh, non-mixed template here).
     *
     * @return \Closure(never): TryTo<mixed>
     */
    private static function witness(): \Closure
    {
        return static function (mixed $value): TryTo {
            throw new \LogicException('witness never invoked');
        };
    }

    /**
     * Completes the associated Future with a success. Throws
     * \LogicException if this Promise was already completed.
     *
     * @param T $value
     */
    public function success(mixed $value): void
    {
        $this->future->complete(new Success($value));
    }

    /**
     * Completes the associated Future with a failure. Throws
     * \LogicException if this Promise was already completed.
     */
    public function failure(\Throwable $error): void
    {
        $this->future->complete(new Failure($error));
    }

    /**
     * @return Future<T>
     */
    public function future(): Future
    {
        return $this->future;
    }
}
