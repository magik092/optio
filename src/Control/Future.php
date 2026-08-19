<?php

declare(strict_types=1);

namespace Optio\Control;

use Optio\Control\TryTo\Failure;
use Optio\Control\TryTo\Success;
use Optio\Tuple\Tuple2;

/**
 * A future value produced by a computation that runs eagerly and
 * synchronously on the same thread: Future::of() executes its closure
 * immediately, before the call even returns. Optio's Future provides no
 * real parallelism or asynchronous I/O — PHP has no built-in event loop —
 * it exists purely to compose the *result* of a computation (success or
 * failure) in a fluent, monadic style, similar to TryTo but with
 * onSuccess/onFailure/onComplete callbacks and Promise-based interop with
 * externally-completed results. The only Future that is observably pending
 * right after creation is one obtained from Promise::make()->future().
 *
 * @template T
 */
final class Future
{
    /**
     * @var TryTo<T>|null
     */
    private ?TryTo $result;

    /**
     * @var list<\Closure(TryTo<T>): void>
     */
    private array $listeners = [];

    /**
     * $witness is never invoked; it exists purely so PHPStan can bind this
     * instance's T when $result is null (a pending Future has no value yet
     * to bind T from), following the project's documented pattern for
     * private constructors backing a generic static factory (see
     * "Wzorzec pisania kodu generycznego pod PHPStan level:max", rule 5).
     *
     * @param TryTo<T>|null                  $result
     * @param \Closure(never): TryTo<T>|null $witness
     */
    private function __construct(?TryTo $result, ?\Closure $witness = null)
    {
        $this->result = $result;
        unset($witness);
    }

    /**
     * Runs $computation immediately, catching any \Throwable into a Failure.
     *
     * @template U
     *
     * @param \Closure(): U $computation
     *
     * @return self<U>
     */
    public static function of(\Closure $computation): self
    {
        return new self(TryTo::run($computation));
    }

    /**
     * Builds an already-completed, successful Future.
     *
     * @template U
     *
     * @param U $value
     *
     * @return self<U>
     */
    public static function successful(mixed $value): self
    {
        return new self(new Success($value));
    }

    /**
     * Builds an already-completed, failed Future.
     *
     * @return self<never>
     */
    public static function failed(\Throwable $error): self
    {
        return new self(new Failure($error));
    }

    /**
     * Builds a pending Future with no result yet. Only Promise::make() (in
     * the same Optio\Control namespace) should call this.
     *
     * @internal
     *
     * @template U
     *
     * @param \Closure(never): TryTo<U> $witness
     *
     * @return self<U>
     */
    public static function pending(\Closure $witness): self
    {
        return new self(null, $witness);
    }

    public function isCompleted(): bool
    {
        return $this->result !== null;
    }

    /**
     * Completes a pending Future exactly once. Called only by
     * Promise::success()/failure() — calling this directly, or calling it
     * twice on the same Future, is a programming error.
     *
     * @internal
     *
     * @param TryTo<T> $result
     */
    public function complete(TryTo $result): void
    {
        if ($this->result !== null) {
            throw new \LogicException('Future is already completed.');
        }

        $this->result = $result;

        $listeners = $this->listeners;
        $this->listeners = [];

        foreach ($listeners as $listener) {
            $listener($result);
        }
    }

    /**
     * Invokes $onReady with the result now if already completed, otherwise
     * queues it to run once complete() is called.
     *
     * @param \Closure(TryTo<T>): void $onReady
     */
    private function whenReady(\Closure $onReady): void
    {
        if ($this->result !== null) {
            $onReady($this->result);

            return;
        }

        $this->listeners[] = $onReady;
    }

    /**
     * Builds a new Future whose result is $transform applied to this one's
     * eventual result — resolved immediately if this Future is already
     * completed, or deferred until it completes otherwise.
     *
     * @template U
     *
     * @param \Closure(TryTo<T>): TryTo<U> $transform
     *
     * @return self<U>
     */
    private function chain(\Closure $transform): self
    {
        if ($this->result !== null) {
            return new self($transform($this->result));
        }

        $next = self::pending($transform);
        $this->whenReady(function (TryTo $result) use ($next, $transform): void {
            $next->complete($transform($result));
        });

        return $next;
    }

    /**
     * @template U
     *
     * @param \Closure(T): U $mapper
     *
     * @return self<U>
     */
    public function map(\Closure $mapper): self
    {
        return $this->chain(function (TryTo $result) use ($mapper): TryTo {
            return $result->map($mapper);
        });
    }

    /**
     * @template U
     *
     * @param \Closure(T): self<U> $mapper
     *
     * @return self<U>
     */
    public function flatMap(\Closure $mapper): self
    {
        $next = self::pending(function (mixed $value) use ($mapper): TryTo {
            return $mapper($value)->toTryTo();
        });

        $this->whenReady(function (TryTo $result) use ($next, $mapper): void {
            if (!$result->isSuccess()) {
                $next->complete($result->fold(
                    function (\Throwable $exception): TryTo {
                        return new Failure($exception);
                    },
                    function (mixed $value): TryTo {
                        throw new \LogicException('unreachable: already checked isSuccess() === false');
                    },
                ));

                return;
            }

            $inner = $result->fold(
                function (\Throwable $exception): ?self {
                    throw $exception;
                },
                function (mixed $value) use ($mapper, $next): ?self {
                    try {
                        return $mapper($value);
                    } catch (\Throwable $exception) {
                        $next->complete(new Failure($exception));

                        return null;
                    }
                },
            );

            if ($inner === null) {
                return;
            }

            $inner->whenReady(function (TryTo $innerResult) use ($next): void {
                $next->complete($innerResult);
            });
        });

        return $next;
    }

    /**
     * Returns the value on success, or re-throws the original \Throwable on
     * failure. Throws \LogicException if this Future has not completed yet
     * (only possible for a pending Promise-backed Future).
     *
     * @return T
     */
    public function get(): mixed
    {
        return $this->toTryTo()->fold(
            function (\Throwable $exception): mixed {
                throw $exception;
            },
            function (mixed $value): mixed {
                return $value;
            },
        );
    }

    /**
     * @template U
     *
     * @param U $default
     *
     * @return T|U
     */
    public function getOrElse(mixed $default): mixed
    {
        if ($this->result === null) {
            return $default;
        }

        return $this->result->fold(
            function (\Throwable $exception) use ($default): mixed {
                return $default;
            },
            function (mixed $value): mixed {
                return $value;
            },
        );
    }

    /**
     * @return TryTo<T>
     */
    public function toTryTo(): TryTo
    {
        if ($this->result === null) {
            throw new \LogicException('Cannot read the result of a Future that has not completed yet.');
        }

        return $this->result;
    }

    /**
     * @return Either<\Throwable, T>
     */
    public function toEither(): Either
    {
        return $this->toTryTo()->toEither();
    }

    /**
     * @return Option<T>
     */
    public function toOption(): Option
    {
        return $this->toTryTo()->toOption();
    }

    /**
     * Runs $action with the value if this Future succeeds. If already
     * completed, runs immediately; otherwise queued until completion. An
     * exception thrown inside $action is NOT caught — it propagates.
     *
     * @param \Closure(T): void $action
     *
     * @return self<T>
     */
    public function onSuccess(\Closure $action): self
    {
        $this->whenReady(function (TryTo $result) use ($action): void {
            $result->fold(
                function (\Throwable $exception): void {
                },
                function (mixed $value) use ($action): void {
                    $action($value);
                },
            );
        });

        return $this;
    }

    /**
     * Runs $action with the exception if this Future fails. An exception
     * thrown inside $action is NOT caught — it propagates.
     *
     * @param \Closure(\Throwable): void $action
     *
     * @return self<T>
     */
    public function onFailure(\Closure $action): self
    {
        $this->whenReady(function (TryTo $result) use ($action): void {
            $result->fold(
                function (\Throwable $exception) use ($action): void {
                    $action($exception);
                },
                function (mixed $value): void {
                },
            );
        });

        return $this;
    }

    /**
     * Runs $action with the TryTo result regardless of success/failure. An
     * exception thrown inside $action is NOT caught — it propagates.
     *
     * @param \Closure(TryTo<T>): void $action
     *
     * @return self<T>
     */
    public function onComplete(\Closure $action): self
    {
        $this->whenReady($action);

        return $this;
    }

    /**
     * Alias for onComplete(), for chaining side effects (e.g. logging).
     *
     * @param \Closure(TryTo<T>): void $action
     *
     * @return self<T>
     */
    public function andThen(\Closure $action): self
    {
        return $this->onComplete($action);
    }

    /**
     * Turns a failure into a success by applying $recovery to the
     * exception; no-op on an already-successful Future.
     *
     * @template U
     *
     * @param \Closure(\Throwable): U $recovery
     *
     * @return self<T|U>
     */
    public function recover(\Closure $recovery): self
    {
        return $this->chain(function (TryTo $result) use ($recovery): TryTo {
            return $result->isSuccess() ? $result : $result->recover($recovery);
        });
    }

    /**
     * Turns a failure into whatever Future $recovery returns; no-op on an
     * already-successful Future.
     *
     * @template U
     *
     * @param \Closure(\Throwable): self<U> $recovery
     *
     * @return self<T|U>
     */
    public function recoverWith(\Closure $recovery): self
    {
        $next = self::pending(function (mixed $v) use ($recovery): TryTo {
            $result = $this->toTryTo();

            return $result->isSuccess() ? $result : $result->fold(
                function (\Throwable $exception) use ($recovery): TryTo {
                    return $recovery($exception)->toTryTo();
                },
                function (mixed $value): TryTo {
                    throw new \LogicException('unreachable: already checked isSuccess() === false');
                },
            );
        });

        $this->whenReady(function (TryTo $result) use ($next, $recovery): void {
            if ($result->isSuccess()) {
                $next->complete($result);

                return;
            }

            $inner = $result->fold(
                function (\Throwable $exception) use ($recovery, $next): ?self {
                    try {
                        return $recovery($exception);
                    } catch (\Throwable $caughtException) {
                        $next->complete(new Failure($caughtException));

                        return null;
                    }
                },
                function (mixed $value): ?self {
                    throw new \LogicException('unreachable: already checked isSuccess() === false');
                },
            );

            if ($inner === null) {
                return;
            }

            $inner->whenReady(function (TryTo $innerResult) use ($next): void {
                $next->complete($innerResult);
            });
        });

        return $next;
    }

    /**
     * Uses $other's result if this Future failed; no-op on success.
     *
     * @template U
     *
     * @param self<U> $other
     *
     * @return self<T|U>
     */
    public function fallbackTo(self $other): self
    {
        $next = self::pending(function (mixed $v) use ($other): TryTo {
            $result = $this->toTryTo();

            return $result->isSuccess() ? $result : $other->toTryTo();
        });

        $this->whenReady(function (TryTo $result) use ($next, $other): void {
            if ($result->isSuccess()) {
                $next->complete($result);

                return;
            }

            $other->whenReady(function (TryTo $otherResult) use ($next): void {
                $next->complete($otherResult);
            });
        });

        return $next;
    }

    /**
     * Combines this Future with $other into a Future of both values as a
     * Tuple2, once both succeed. The first failure encountered (this one,
     * checked first) wins.
     *
     * @template U
     *
     * @param self<U> $other
     *
     * @return self<Tuple2<T, U>>
     */
    public function zip(self $other): self
    {
        return $this->flatMap(fn (mixed $value): self => $other->map(
            fn (mixed $otherValue): Tuple2 => new Tuple2($value, $otherValue),
        ));
    }

    /**
     * Collects a list of Futures into a Future of a list, in iteration
     * order. The first failure encountered wins.
     *
     * @template U of mixed = mixed
     *
     * @param iterable<self<U>> $futures
     *
     * @return self<list<U>>
     */
    public static function sequence(iterable $futures): self
    {
        $asList = [];
        foreach ($futures as $future) {
            $asList[] = $future;
        }

        return self::sequenceList($asList);
    }

    /**
     * Recursive helper for sequence(): processes a plain list (rather than
     * an arbitrary iterable, or a mutable accumulator reassigned in a loop)
     * so PHPStan can verify the generic type of each call in isolation
     * instead of computing a loop fixpoint.
     *
     * @template U
     *
     * @param list<self<U>> $futures
     *
     * @return self<list<U>>
     */
    private static function sequenceList(array $futures): self
    {
        $first = array_shift($futures);
        $rest = $futures;

        if ($first === null) {
            // $futures is empty here, but its declared type (list<self<U>>)
            // — unnarrowed, since we branched on $first rather than
            // $futures itself — is what lets PHPStan bind U for this
            // otherwise-valueless list.
            $next = self::pending(function () use ($futures): TryTo {
                $list = [];
                foreach ($futures as $future) {
                    $list[] = $future->get();
                }

                return new Success($list);
            });

            $next->complete(new Success([]));

            return $next;
        }

        return $first->flatMap(
            fn (mixed $value): self => self::sequenceList($rest)->map(
                fn (array $tail): array => self::prepend($value, $tail),
            ),
        );
    }

    /**
     * @template U
     *
     * @param U       $value
     * @param list<U> $tail
     *
     * @return list<U>
     */
    private static function prepend(mixed $value, array $tail): array
    {
        return array_merge([$value], $tail);
    }
}
