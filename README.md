# Optio

Immutable, monadic functional programming for PHP 8.1+, inspired by [Vavr](https://www.vavr.io/).

Optio is a spiritual successor to `munusphp/munus` (itself inspired by Vavr,
formerly Javaslang), rebuilt from scratch to fix problems the original
never solved: linear-scan collections instead of real hash structures, and
type guarantees that only look strong until you check what happens at
runtime. Optio is for PHP developers who want functional idioms — `Option`
instead of `null`, `Either`/`TryTo`/`Validation` instead of exceptions as
control flow, immutable collections instead of defensive copying — backed
by real static type safety through PHPStan `level: max`.

## Requirements

PHP 8.1 or newer. Optio relies on `readonly` properties, `enum`, `match`
and first-class callable syntax, all used to keep the implementation
immutable and expressive.

## Installation

```bash
composer require magik092/optio
```

## Monads

### Option

Makes absence explicit instead of relying on `null`.

```php
use Optio\Control\Option;

$name = Option::of($_GET['name'] ?? null)   // null -> None, otherwise Some
    ->map(fn (string $n): string => trim($n))
    ->filter(fn (string $n): bool => $n !== '')
    ->getOrElse('anonymous');
```

### Either

Right-biased: `map`/`flatMap` operate on `Right`, `Left` (conventionally an
error) passes through unchanged.

```php
use Optio\Control\Either;

$message = Either::right(10)
    ->map(fn (int $n): int => $n * 2)
    ->fold(
        fn (string $error): string => "error: {$error}",
        fn (int $value): string => "ok: {$value}",
    );
// "ok: 20"
```

### TryTo

Wraps a computation that may throw. Named `TryTo` (not `Try`) because `Try`
is a reserved PHP keyword. Exceptions thrown inside `map`/`flatMap` are
caught automatically and turned into a `Failure`.

```php
use Optio\Control\TryTo;

$value = TryTo::run(fn (): int => intdiv(10, 0))
    ->recover(fn (\Throwable $e): int => 0)
    ->fold(
        fn (\Throwable $e): string => 'failure',
        fn (int $v): string => "value: {$v}",
    );
// "value: 0"
```

### Validation

Like `Either`, but built for accumulating errors instead of failing fast —
useful for validating several form/DTO fields at once and reporting every
problem, not just the first one.

```php
use Optio\Control\Validation;

$email = Validation::valid('a@b.com');
$age = Validation::invalid('age must be a number');

$result = $email->combine($age, fn (string $e, int $a): array => [$e, $a])
    ->fold(
        fn (array $errors): string => 'errors: ' . implode(', ', $errors),
        fn (array $v): string => 'ok',
    );
// "errors: age must be a number"
```

### Lazy

A memoized computation: the supplier runs at most once, on the first `get()`.

```php
use Optio\Control\Lazy;

$lazy = Lazy::of(function (): int {
    echo "computing...\n";
    return 42;
});

$lazy->get(); // prints "computing...", returns 42
$lazy->get(); // returns 42, no recomputation
```

Conversions between monads (`toOption()`, `toEither()`, `toTryTo()`) are
always explicit method calls — a chain never silently jumps from one monad
type to another.

## Collections

### HashMap and HashSet

Both are backed by a real HAMT (Hash Array Mapped Trie, 32-way branching,
path copying) — not a linear array scan. Mutating operations (`put`, `add`,
`remove`) return a new, structurally-shared instance in `O(log32 n)`, they
never copy the whole structure.

```php
use Optio\Collection\HashMap;
use Optio\Collection\HashSet;

$map = HashMap::empty()
    ->put('a', 1)
    ->put('b', 2);

$map->get('a')->getOrElse(0); // 1
$map->length();               // 2

$roles = HashSet::of('admin', 'editor')->add('viewer');
$roles->contains('viewer'); // true
```

Custom objects can be used as keys/elements as long as they implement
`Optio\Value\Hashable`.

Objects that can't (or shouldn't) implement `Hashable` can instead supply a
`Hasher` — a plain closure the collection remembers and uses for every
subsequent operation, instead of requiring the object to know how to hash
itself:

```php
final class Person
{
    public function __construct(
        public readonly string $name,
        public readonly int $age,
    ) {
    }
}

$hasher = fn (Person $p): string => $p->name.':'.$p->age;

$people = HashSet::ofHashed(
    $hasher,
    new Person('Karol', 33),
    new Person('Karol', 33), // deduplicated — same hash
);
$people->length(); // 1
```

`filter()` and `HashMap::keys()` carry the hasher forward (the element/key
type doesn't change); `map()` resets it to the default `Hashable`-based
hashing, since the mapped type might be different — use `mapHashed()`
instead to supply a new hasher for the mapped type in one step.

`HashMap`, `HashSet`, `Vector` and `LinkedList` all support `sliding()`/
`grouped()`, chunking the collection into a `Vector` of same-type windows:

```php
$map = HashMap::empty()->put('a', 1)->put('b', 2)->put('c', 3);

$map->grouped(2)->length(); // 2 windows — window membership follows hash order, not insertion order
```

### Vector and LinkedList

`Vector` is an indexed sequence backed by a 32-way branching trie with
path-copying, giving O(log32 n) `get`/`update`/`append` — not the O(n)
linear scan munusphp's `GenericList` relied on. `LinkedList` is a classic
immutable Cons/Nil chain with O(1) `prepend`/`head`/`tail`. Use `Vector`
when you need indexed access or append; use `LinkedList` when you need
O(1) prepend/head/tail instead. (The public class is named `LinkedList`,
not `List` — `list` is a reserved word in PHP.)

```php
use Optio\Collection\Vector;

$vector = Vector::of(1, 2, 3)->append(4);
$vector->get(3);   // 4
$vector->length(); // 4
```

```php
use Optio\Collection\LinkedList;

$list = LinkedList::of(1, 2, 3)->prepend(0);
$list->head();     // 0
$list->toArray();  // [0, 1, 2, 3]
```

### Tuple

`Tuple0` through `Tuple8`, accessed either positionally (`ArrayAccess`) or
via generated named methods.

```php
use Optio\Tuple;

$pair = Tuple::of('x', 1);
$pair[0]; // 'x'
$pair[1]; // 1
```

### Stream

A lazy, possibly infinite sequence: elements are computed on demand, so
`map`/`filter` can be chained over infinite generators as long as the
final consumer (`take`, `toArray`, ...) stays finite.

```php
use Optio\Collection\Stream;

$stream = Stream::iterate(1, fn (int $n): int => $n + 1)
    ->filter(fn (int $n): bool => $n % 2 === 0)
    ->take(3);

$stream->toArray(); // [2, 4, 6]
```

### Matcher

Runtime pattern matching by type: dispatches to the first `case()` whose
class matches the value's actual type, falling back to `default()` if
none matches. (The public class is named `Matcher`, not `Match` — `match`
is a reserved expression keyword in PHP 8.0+.)

```php
use Optio\Control\Option\None;
use Optio\Control\Option\Some;
use Optio\Value\Matcher;

$label = Matcher::value($value)
    ->case(Some::class, fn (Some $some): string => 'present')
    ->case(None::class, fn (None $none): string => 'absent')
    ->default(fn (mixed $v): string => 'unknown')
    ->get();
```

### Future / Promise

`Future::of()` runs its closure eagerly and synchronously — the computation
finishes before the call returns — so `map`/`flatMap` compose the *result*
of a computation the way `TryTo` does, but with `onSuccess`/`onFailure`/
`onComplete` callbacks. Optio's `Future` has no real concurrency: it is
eager, single-threaded and backed by no event loop, unless it is instead
obtained from `Promise::make()`, a writable handle whose `success()`/
`failure()` complete the associated (initially pending) `Future` exactly
once, typically from callback-based code that doesn't itself produce a
`Future`.

```php
use Optio\Control\Future;
use Optio\Control\Promise;

$future = Future::of(fn (): int => 2 + 2)->map(fn (int $n): int => $n * 10);
$future->get(); // 40

$promise = Promise::make();
$pending = $promise->future();
$pending->isCompleted(); // false
$promise->success(42);
$pending->get(); // 42
```

## Philosophy

Everything here is immutable — operations return new instances rather than
mutating in place — and every monadic method is fully generic
(`@template`/`@template-covariant`) so PHPStan `level: max` catches type
mistakes, like a `flatMap` callback returning the wrong type, at analysis
time instead of at runtime. PHP has no higher-kinded types, so this only
gets you so far: `flatMap` takes a typed `\Closure` instead of a bare
`callable` because that's as close as PHP's type system gets, and the
guarantee is entirely static — there's no `assert()` backstop checking
things at runtime.

## Examples

The [`examples/`](examples/) directory has three complete, runnable scripts
that go beyond the snippets above:

- `01_registration_pipeline.php` — validating and registering a user with
  `Validation`, `Either` and `HashMap`/`HashSet`.
- `02_event_ledger.php` — an append-only event ledger built on immutable
  collections.
- `03_recommendations.php` — a small recommendation engine composing
  `Option`, `HashMap` and `HashSet`.

Run any of them with `php examples/01_registration_pipeline.php`.

## License

MIT — see [`LICENSE`](LICENSE).

## Acknowledgements

- [Vavr](https://www.vavr.io/) — the library Optio's API is modeled on.
- [munusphp/munus](https://github.com/munusphp/munus) — the PHP port Optio
  succeeds; several ideas (the `Tuple` generator, the overall shape of the
  Control layer) are carried over directly.
- [freyr/monadic](https://github.com/freyr/monadic) — inspiration for the
  `abstract class` + `final` variant pattern (`Some`/`None`, `Ok`/`Err`)
  used throughout Optio's Control layer.
