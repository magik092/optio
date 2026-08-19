<?php

declare(strict_types=1);

/**
 * Example 1: user registration pipeline.
 *
 * Raw input -> Validation (accumulates ALL field errors) -> Either (business
 * rule: e-mail must be unique) -> HashMap<string, User> as the "database"
 * -> HashSet of roles -> Option lookups -> summary report.
 *
 * Run: php8.1 examples/01_registration_pipeline.php
 */

require __DIR__.'/../vendor/autoload.php';

use Optio\Collection\HashMap;
use Optio\Collection\HashSet;
use Optio\Control\Either;
use Optio\Control\Option;
use Optio\Control\Validation;
use Optio\Tuple;
use Optio\Tuple\Tuple2;

final class User
{
    /**
     * @param HashSet<string> $roles
     */
    public function __construct(
        public readonly string $email,
        public readonly string $name,
        public readonly int $age,
        public readonly HashSet $roles,
    ) {
    }

    public function describe(): string
    {
        $roles = implode(', ', $this->roles->toArray());

        return sprintf('%s <%s>, %d y/o, roles: [%s]', $this->name, $this->email, $this->age, $roles);
    }
}

/**
 * @return Validation<string, string>
 */
function validateEmail(mixed $raw): Validation
{
    if (!is_string($raw) || '' === trim($raw)) {
        return Validation::invalid('email: is required');
    }
    if (false === filter_var($raw, FILTER_VALIDATE_EMAIL)) {
        return Validation::invalid(sprintf('email: "%s" is not a valid address', $raw));
    }

    return Validation::valid(strtolower(trim($raw)));
}

/**
 * @return Validation<string, string>
 */
function validateName(mixed $raw): Validation
{
    if (!is_string($raw) || mb_strlen(trim($raw)) < 2) {
        return Validation::invalid('name: must be at least 2 characters long');
    }

    return Validation::valid(trim($raw));
}

/**
 * @return Validation<string, int>
 */
function validateAge(mixed $raw): Validation
{
    if (!is_numeric($raw)) {
        return Validation::invalid('age: must be a number');
    }
    $age = (int) $raw;
    if ($age < 18) {
        return Validation::invalid(sprintf('age: %d is below the required minimum of 18', $age));
    }
    if ($age > 130) {
        return Validation::invalid(sprintf('age: %d is not a plausible age', $age));
    }

    return Validation::valid($age);
}

/**
 * @return Validation<string, HashSet<string>>
 */
function validateRoles(mixed $raw): Validation
{
    $allowed = HashSet::of('admin', 'editor', 'viewer');
    if (!is_array($raw) || [] === $raw) {
        return Validation::invalid('roles: at least one role is required');
    }

    $unknown = HashSet::ofAll($raw)->filter(static fn (mixed $role): bool => !$allowed->contains($role));
    if (!$unknown->isEmpty()) {
        return Validation::invalid(sprintf('roles: unknown role(s) %s', implode(', ', $unknown->toArray())));
    }

    return Validation::valid(HashSet::ofAll($raw));
}

/**
 * Builds a User out of four independent validations, accumulating every error.
 *
 * @return Validation<string, User>
 */
function validateUser(array $input): Validation
{
    return validateEmail($input['email'] ?? null)
        ->combine(
            validateName($input['name'] ?? null),
            static fn (string $email, string $name): Tuple2 => Tuple::of($email, $name),
        )
        ->combine(
            validateAge($input['age'] ?? null),
            static fn (Tuple2 $pair, int $age): array => [$pair[0], $pair[1], $age],
        )
        ->combine(
            validateRoles($input['roles'] ?? null),
            static fn (array $parts, HashSet $roles): User => new User($parts[0], $parts[1], $parts[2], $roles),
        );
}

/**
 * Business rule that only makes sense once the shape is valid.
 *
 * @param HashMap<string, User> $registry
 *
 * @return Either<string, User>
 */
function ensureUnique(HashMap $registry, User $user): Either
{
    return $registry->get($user->email)->fold(
        static fn (): Either => Either::right($user),
        static fn (User $existing): Either => Either::left(
            sprintf('e-mail %s is already taken by %s', $existing->email, $existing->name),
        ),
    );
}

$submissions = [
    ['email' => 'Ada@Example.COM', 'name' => 'Ada Lovelace', 'age' => 36, 'roles' => ['admin', 'editor', 'admin']],
    ['email' => 'not-an-email', 'name' => 'X', 'age' => 12, 'roles' => ['wizard']],
    ['email' => 'grace@example.com', 'name' => 'Grace Hopper', 'age' => 45, 'roles' => ['editor']],
    ['email' => 'ada@example.com', 'name' => 'Ada Impostor', 'age' => 30, 'roles' => ['viewer']],
    ['email' => 'alan@example.com', 'name' => 'Alan Turing', 'age' => '41', 'roles' => ['viewer', 'editor']],
    ['name' => 'Anonymous', 'age' => 200, 'roles' => []],
];

echo "=== Registration pipeline ===\n\n";

/** @var HashMap<string, User> $registry */
$registry = HashMap::empty();
/** @var list<string> $rejections */
$rejections = [];

/**
 * Small helper: renders an error list as one line.
 *
 * @param list<string> $errors
 */
function renderErrors(array $errors): string
{
    return implode(' | ', $errors);
}

foreach ($submissions as $index => $input) {
    $label = sprintf('#%d', $index + 1);

    /** @var Either<string, User> $result */
    $result = validateUser($input)
        ->toEither()
        ->fold(
            static fn (array $errors): Either => Either::left(renderErrors($errors)),
            static fn (User $user): Either => ensureUnique($registry, $user),
        );

    $registry = $result->fold(
        function (string $error) use ($label, $registry, &$rejections): HashMap {
            $rejections[] = sprintf('%s %s', $label, $error);
            printf("%s REJECTED  %s\n", $label, $error);

            return $registry;
        },
        function (User $user) use ($label, $registry): HashMap {
            printf("%s ACCEPTED  %s\n", $label, $user->describe());

            return $registry->put($user->email, $user);
        },
    );
}

echo "\n=== Registry state ===\n";
printf("users stored: %d\n", $registry->length());

$allRoles = $registry->fold(
    HashSet::empty(),
    static fn (HashSet $acc, Tuple2 $entry): HashSet => $entry[1]->roles->fold(
        $acc,
        static fn (HashSet $inner, string $role): HashSet => $inner->add($role),
    ),
);
printf("distinct roles in use: %s\n", implode(', ', $allRoles->toArray()));

$admins = $registry->filter(static fn (Tuple2 $entry): bool => $entry[1]->roles->contains('admin'));
printf("admins: %s\n", implode(', ', array_map(
    static fn (User $u): string => $u->name,
    $admins->values(),
)));

echo "\n=== Lookups (Option, no null checks) ===\n";
foreach (['grace@example.com', 'nobody@example.com'] as $email) {
    $line = $registry->get($email)
        ->map(static fn (User $u): string => $u->describe())
        ->getOrElse(sprintf('no account for %s', $email));
    printf("- %s\n", $line);
}

$averageAge = $registry->isEmpty()
    ? Option::none()
    : Option::some(array_sum(array_map(static fn (User $u): int => $u->age, $registry->values())) / $registry->length());

printf(
    "\naverage age of registered users: %s\n",
    $averageAge->fold(
        static fn (): string => 'n/a (registry empty)',
        static fn (float $avg): string => number_format($avg, 1),
    ),
);

printf("rejected submissions: %d\n", count($rejections));
