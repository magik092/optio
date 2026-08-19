<?php

declare(strict_types=1);

/**
 * Example 2: replaying an event log into account balances.
 *
 * Raw log lines -> TryTo (decoding may blow up) -> Option (does the account
 * exist?) -> Either (business rules: overdraft, frozen account) -> HashMap
 * keyed by a Hashable value object holding the projected state.
 *
 * Run: php8.1 examples/02_event_ledger.php
 */

require __DIR__.'/../vendor/autoload.php';

use Optio\Collection\HashMap;
use Optio\Collection\HashSet;
use Optio\Control\Either;
use Optio\Control\Option;
use Optio\Control\TryTo;
use Optio\Tuple;
use Optio\Tuple\Tuple2;
use Optio\Value\Comparable;
use Optio\Value\Hashable;

final class AccountId implements Hashable, Comparable
{
    public function __construct(public readonly string $value)
    {
    }

    public function hashCode(): string
    {
        return 'acc:'.$this->value;
    }

    public function equals(mixed $other): bool
    {
        return $other instanceof self && $other->value === $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}

final class Account
{
    public function __construct(
        public readonly AccountId $id,
        public readonly string $owner,
        public readonly int $balance,
        public readonly bool $frozen = false,
    ) {
    }

    public function withBalance(int $balance): self
    {
        return new self($this->id, $this->owner, $balance, $this->frozen);
    }

    public function frozen(): self
    {
        return new self($this->id, $this->owner, $this->balance, true);
    }
}

final class Event
{
    public function __construct(
        public readonly string $type,
        public readonly AccountId $account,
        public readonly int $amount,
    ) {
    }
}

/**
 * Decoding can fail in many ways; TryTo turns all of them into a value.
 *
 * @return TryTo<Event>
 */
function decodeEvent(string $line): TryTo
{
    return TryTo::run(static function () use ($line): Event {
        /** @var mixed $decoded */
        $decoded = json_decode($line, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new InvalidArgumentException('event is not a JSON object');
        }
        foreach (['type', 'account'] as $required) {
            if (!isset($decoded[$required])) {
                throw new InvalidArgumentException(sprintf('missing field "%s"', $required));
            }
        }
        $amount = $decoded['amount'] ?? 0;
        if (!is_int($amount)) {
            throw new InvalidArgumentException('amount must be an integer number of cents');
        }

        return new Event((string) $decoded['type'], new AccountId((string) $decoded['account']), $amount);
    });
}

/**
 * @param HashMap<AccountId, Account> $accounts
 *
 * @return Either<string, Account>
 */
function applyEvent(HashMap $accounts, Event $event): Either
{
    /** @var Either<string, Account> $found */
    $found = $accounts->get($event->account)->toEither(
        sprintf('unknown account %s', $event->account),
    );

    return $found
        ->flatMap(static fn (Account $account): Either => $account->frozen
            ? Either::left(sprintf('account %s is frozen', $account->id))
            : Either::right($account))
        ->flatMap(static fn (Account $account): Either => match ($event->type) {
            'deposit' => $event->amount > 0
                ? Either::right($account->withBalance($account->balance + $event->amount))
                : Either::left('deposit amount must be positive'),
            'withdrawal' => $event->amount > $account->balance
                ? Either::left(sprintf(
                    'insufficient funds on %s: balance %s, requested %s',
                    $account->id,
                    money($account->balance),
                    money($event->amount),
                ))
                : Either::right($account->withBalance($account->balance - $event->amount)),
            'freeze' => Either::right($account->frozen()),
            default => Either::left(sprintf('unsupported event type "%s"', $event->type)),
        });
}

function money(int $cents): string
{
    return number_format($cents / 100, 2).' PLN';
}

$accounts = HashMap::ofAll([
    Tuple::of(new AccountId('ACC-1'), new Account(new AccountId('ACC-1'), 'Ada', 100_00)),
    Tuple::of(new AccountId('ACC-2'), new Account(new AccountId('ACC-2'), 'Grace', 250_00)),
    Tuple::of(new AccountId('ACC-3'), new Account(new AccountId('ACC-3'), 'Alan', 0)),
]);

$log = [
    '{"type":"deposit","account":"ACC-3","amount":5000}',
    '{"type":"withdrawal","account":"ACC-1","amount":2500}',
    '{"type":"withdrawal","account":"ACC-1","amount":999999}',
    'this is not json at all',
    '{"type":"deposit","account":"ACC-9","amount":100}',
    '{"type":"freeze","account":"ACC-2"}',
    '{"type":"deposit","account":"ACC-2","amount":1000}',
    '{"type":"teleport","account":"ACC-3","amount":1}',
    '{"type":"deposit","account":"ACC-3","amount":"a lot"}',
    '{"type":"withdrawal","account":"ACC-3","amount":1500}',
];

echo "=== Replaying event log ===\n\n";

/** @var HashSet<string> $rejectedAccounts */
$rejectedAccounts = HashSet::empty();
$applied = 0;
$skipped = 0;

foreach ($log as $offset => $line) {
    /** @var Either<string, Tuple2<Event, Account>> $outcome */
    $outcome = decodeEvent($line)
        ->toEither()
        ->fold(
            static fn (\Throwable $e): Either => Either::left('malformed event: '.$e->getMessage()),
            static fn (Event $event): Either => applyEvent($accounts, $event)
                ->map(static fn (Account $account): Tuple2 => Tuple::of($event, $account)),
        );

    $accounts = $outcome->fold(
        function (string $error) use ($offset, $accounts, &$skipped, &$rejectedAccounts): HashMap {
            ++$skipped;
            printf("offset %d  SKIP   %s\n", $offset, $error);
            if (1 === preg_match('/ACC-\d+/', $error, $m)) {
                $rejectedAccounts = $rejectedAccounts->add($m[0]);
            }

            return $accounts;
        },
        function (Tuple2 $pair) use ($offset, $accounts, &$applied): HashMap {
            ++$applied;
            /** @var Event $event */
            $event = $pair[0];
            /** @var Account $account */
            $account = $pair[1];
            printf(
                "offset %d  APPLY  %-11s %s -> balance %s%s\n",
                $offset,
                $event->type,
                $account->id,
                money($account->balance),
                $account->frozen ? ' (frozen)' : '',
            );

            return $accounts->put($account->id, $account);
        },
    );
}

echo "\n=== Projection ===\n";
foreach ($accounts->toArray() as $entry) {
    /** @var Account $account */
    $account = $entry[1];
    printf("%-6s %-6s %12s%s\n", $account->id, $account->owner, money($account->balance), $account->frozen ? '  [frozen]' : '');
}

$total = $accounts->fold(0, static fn (int $sum, Tuple2 $entry): int => $sum + $entry[1]->balance);
printf("\ntotal held: %s\n", money($total));
printf("events applied: %d, skipped: %d\n", $applied, $skipped);
printf(
    "accounts mentioned in rejections: %s\n",
    $rejectedAccounts->isEmpty() ? '(none)' : implode(', ', $rejectedAccounts->toArray()),
);

echo "\n=== Recovering from a bad line instead of skipping it ===\n";
$recovered = decodeEvent('{"broken"')
    ->recover(static fn (\Throwable $e): Event => new Event('noop', new AccountId('ACC-3'), 0))
    ->map(static fn (Event $e): string => sprintf('recovered as "%s" on %s', $e->type, $e->account))
    ->fold(
        static fn (\Throwable $e): string => 'still failing: '.$e->getMessage(),
        static fn (string $message): string => $message,
    );
printf("%s\n", $recovered);

printf(
    "and as an Option: %s\n",
    decodeEvent('{"broken"')->toOption()->fold(
        static fn (): string => 'None - the line carried no usable event',
        static fn (Event $e): string => 'Some('.$e->type.')',
    ),
);
