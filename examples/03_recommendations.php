<?php

declare(strict_types=1);

/**
 * Example 3: a tiny product recommendation engine.
 *
 * HashSet gives set algebra over purchase history (dedup, intersection),
 * Lazy defers an "expensive" similarity computation until something really
 * needs it (and memoizes it), HashMap holds the catalogue and the scores,
 * Option models "this customer is unknown / has no history", and Tuple2
 * carries (product, score) pairs through the ranking step.
 *
 * Run: php8.1 examples/03_recommendations.php
 */

require __DIR__.'/../vendor/autoload.php';

use Optio\Collection\HashMap;
use Optio\Collection\HashSet;
use Optio\Control\Either;
use Optio\Control\Lazy;
use Optio\Control\Option;
use Optio\Tuple;
use Optio\Tuple\Tuple2;

final class Product
{
    /**
     * @param HashSet<string> $tags
     */
    public function __construct(
        public readonly string $sku,
        public readonly string $name,
        public readonly int $priceCents,
        public readonly HashSet $tags,
    ) {
    }
}

/** @var HashMap<string, Product> $catalogue */
$catalogue = HashMap::ofAll(array_map(
    static fn (array $row): Tuple2 => Tuple::of(
        $row[0],
        new Product($row[0], $row[1], $row[2], HashSet::ofAll($row[3])),
    ),
    [
        ['P-01', 'Espresso beans 1kg', 8900, ['coffee', 'grocery']],
        ['P-02', 'Moka pot', 12900, ['coffee', 'kitchen']],
        ['P-03', 'Milk frother', 9900, ['coffee', 'kitchen', 'gadget']],
        ['P-04', 'Cast iron pan', 24900, ['kitchen']],
        ['P-05', 'Mechanical keyboard', 45900, ['gadget', 'office']],
        ['P-06', 'Desk lamp', 15900, ['office']],
        ['P-07', 'Notebook A5', 2900, ['office', 'stationery']],
        ['P-08', 'Fountain pen', 18900, ['office', 'stationery', 'gadget']],
    ],
));

/** @var HashMap<string, list<string>> $history raw purchase history, duplicates included */
$history = HashMap::ofAll([
    ['ada', ['P-01', 'P-02', 'P-01', 'P-03']],
    ['grace', ['P-05', 'P-07', 'P-08', 'P-07']],
    ['alan', []],
]);

$similarityCalls = 0;

/**
 * Pretends to be expensive: Jaccard similarity over tag sets.
 */
$similarity = static function (Product $a, Product $b) use (&$similarityCalls): float {
    ++$similarityCalls;
    $union = $a->tags->fold($b->tags, static fn (HashSet $acc, string $tag): HashSet => $acc->add($tag));
    $intersection = $a->tags->filter(static fn (string $tag): bool => $b->tags->contains($tag));

    return 0 === $union->length() ? 0.0 : $intersection->length() / $union->length();
};

/**
 * The customer's deduplicated basket, or None when we know nothing about them.
 *
 * @param HashMap<string, list<string>> $history
 *
 * @return Option<HashSet<string>>
 */
function ownedSkus(HashMap $history, string $customer): Option
{
    return $history->get($customer)
        ->map(static fn (array $skus): HashSet => HashSet::ofAll($skus))
        ->filter(static fn (HashSet $set): bool => !$set->isEmpty());
}

/**
 * Builds a *lazy* score for every candidate product. Nothing is computed here;
 * only the ranking step below forces the ones it needs.
 *
 * @param HashMap<string, Product> $catalogue
 * @param HashSet<string>          $owned
 *
 * @return HashMap<string, Lazy<float>>
 */
function lazyScores(HashMap $catalogue, HashSet $owned, \Closure $similarity): HashMap
{
    $ownedProducts = array_values(array_filter(
        $catalogue->values(),
        static fn (Product $p): bool => $owned->contains($p->sku),
    ));

    return $catalogue
        ->filter(static fn (Tuple2 $entry): bool => !$owned->contains($entry[0]))
        ->map(static fn (Tuple2 $entry): Tuple2 => Tuple::of(
            $entry[0],
            Lazy::of(static function () use ($entry, $ownedProducts, $similarity): float {
                /** @var Product $candidate */
                $candidate = $entry[1];
                $scores = array_map(
                    static fn (Product $owned): float => $similarity($owned, $candidate),
                    $ownedProducts,
                );

                return [] === $scores ? 0.0 : max($scores);
            }),
        ));
}

/**
 * @param HashMap<string, Lazy<float>> $scores
 * @param HashMap<string, Product>     $catalogue
 *
 * @return list<Tuple2<Product, float>>
 */
function rank(HashMap $scores, HashMap $catalogue, int $limit): array
{
    $pairs = $scores->fold([], static function (array $acc, Tuple2 $entry) use ($catalogue): array {
        /** @var Lazy<float> $lazy */
        $lazy = $entry[1];
        $score = $lazy->get();                          // forced here, and only here
        if ($score <= 0.0) {
            return $acc;                                // never even asked twice
        }
        $product = $catalogue->get($entry[0])->getOrElse(null);
        if (!$product instanceof Product) {
            return $acc;
        }
        $acc[] = Tuple::of($product, $score);

        return $acc;
    });

    usort($pairs, static fn (Tuple2 $a, Tuple2 $b): int => [$b[1], $a[0]->priceCents] <=> [$a[1], $b[0]->priceCents]);

    return array_slice($pairs, 0, $limit);
}

/**
 * @param HashMap<string, list<string>> $history
 * @param HashMap<string, Product>      $catalogue
 *
 * @return Either<string, list<Tuple2<Product, float>>>
 */
function recommend(HashMap $history, HashMap $catalogue, string $customer, \Closure $similarity): Either
{
    return ownedSkus($history, $customer)
        ->toEither(sprintf('no usable purchase history for "%s" - falling back to bestsellers', $customer))
        ->map(static fn (HashSet $owned): array => rank(lazyScores($catalogue, $owned, $similarity), $catalogue, 3));
}

function money(int $cents): string
{
    return number_format($cents / 100, 2).' PLN';
}

echo "=== Catalogue ===\n";
$catalogue->forEach(static function (Tuple2 $entry): void {
    /** @var Product $p */
    $p = $entry[1];
    printf("%-5s %-22s %10s  [%s]\n", $p->sku, $p->name, money($p->priceCents), implode(' ', $p->tags->toArray()));
});

echo "\n=== Recommendations ===\n";
foreach (['ada', 'grace', 'alan', 'ghost'] as $customer) {
    printf("\n-- %s --\n", $customer);

    $before = $similarityCalls;
    $result = recommend($history, $catalogue, $customer, $similarity);

    printf("   owns: %s\n", ownedSkus($history, $customer)->fold(
        static fn (): string => '(nothing on record)',
        static fn (HashSet $owned): string => implode(', ', $owned->toArray()),
    ));

    echo $result->fold(
        static fn (string $reason): string => sprintf("   %s\n   -> %s\n", $reason, bestsellers($catalogue)),
        static function (array $ranked): string {
            if ([] === $ranked) {
                return "   nothing left to suggest - they own the whole shelf\n";
            }
            $lines = '';
            foreach ($ranked as $i => $pair) {
                /** @var Product $product */
                $product = $pair[0];
                $lines .= sprintf(
                    "   %d. %-22s %10s  match %.2f\n",
                    $i + 1,
                    $product->name,
                    money($product->priceCents),
                    $pair[1],
                );
            }

            return $lines;
        },
    );
    printf("   similarity computations spent: %d\n", $similarityCalls - $before);
}

/**
 * @param HashMap<string, Product> $catalogue
 */
function bestsellers(HashMap $catalogue): string
{
    $cheap = $catalogue
        ->filter(static fn (Tuple2 $entry): bool => $entry[1]->priceCents < 15000)
        ->values();
    usort($cheap, static fn (Product $a, Product $b): int => $a->priceCents <=> $b->priceCents);

    return implode(', ', array_map(static fn (Product $p): string => $p->name, array_slice($cheap, 0, 3)));
}

echo "\n=== Lazy is memoized ===\n";
$evaluations = 0;
$expensive = Lazy::of(static function () use (&$evaluations): string {
    ++$evaluations;

    return 'computed once';
});
printf("before get(): isEvaluated=%s\n", $expensive->isEvaluated() ? 'true' : 'false');
printf("get() x3: %s / %s / %s\n", $expensive->get(), $expensive->get(), $expensive->get());
printf("after get(): isEvaluated=%s, supplier ran %d time(s)\n", $expensive->isEvaluated() ? 'true' : 'false', $evaluations);

echo "\n=== Set algebra over baskets ===\n";
$ada = ownedSkus($history, 'ada')->getOrElse(HashSet::empty());
$grace = ownedSkus($history, 'grace')->getOrElse(HashSet::empty());
$shared = $ada->filter(static fn (string $sku): bool => $grace->contains($sku));
printf("ada: %s\n", implode(', ', $ada->toArray()));
printf("grace: %s\n", implode(', ', $grace->toArray()));
printf("shared: %s\n", $shared->isEmpty() ? '(disjoint tastes)' : implode(', ', $shared->toArray()));
printf("total distinct similarity computations in this run: %d\n", $similarityCalls);
