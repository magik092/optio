<?php

declare(strict_types=1);

/**
 * Example 6: Vector — an indexed sequence that never deduplicates.
 *
 * HashSet exists to answer "what are the distinct elements", and it needs
 * Hashable (or a Hasher) to know what "distinct" means. Vector answers a
 * different question: "what happened, in order, including repeats". A job
 * queue is a good example — the same job name can legitimately be enqueued
 * several times (a retry, a scheduled repeat, an unrelated request for the
 * same report), and silently dropping those repeats would be a bug, not a
 * feature. Vector::of()/ofAll() keep every element, in insertion order,
 * with no Hashable requirement at all.
 *
 * Run: php8.1 examples/06_vector_basics.php
 */

require __DIR__.'/../vendor/autoload.php';

use Optio\Collection\HashSet;
use Optio\Collection\Vector;

final class QueuedJob
{
    public function __construct(
        public readonly string $name,
        public readonly int $priority,
    ) {
    }
}

$queue = Vector::of(
    new QueuedJob('send-invoice', 5),
    new QueuedJob('rebuild-thumbnails', 3),
    new QueuedJob('send-invoice', 5),
    new QueuedJob('sync-inventory', 1),
    new QueuedJob('send-invoice', 5),
    new QueuedJob('rebuild-thumbnails', 3),
);

printf("Queue has %d entries — every enqueue counts, including the three send-invoice repeats.\n\n", $queue->length());

// The same jobs run through HashSet::ofHashed() would collapse to distinct
// (name, priority) pairs and quietly lose how many times each was actually
// requested — exactly the wrong tool for a queue.
$byNameAndPriority = fn (QueuedJob $job): string => $job->name.'@'.$job->priority;
$distinctJobs = HashSet::ofHashed($byNameAndPriority, ...$queue->toArray());
printf(
    "HashSet::ofHashed() on the same jobs collapses %d entries down to %d distinct ones — wrong for a queue.\n\n",
    $queue->length(),
    $distinctJobs->length(),
);

// append() adds to the end, get() reads by position — insertion order holds.
$queue = $queue->append(new QueuedJob('sync-inventory', 1));
printf("After append(), entry 0 is still '%s' and entry %d is the new '%s'.\n\n", $queue->get(0)->name, $queue->length() - 1, $queue->get($queue->length() - 1)->name);

// map() can project every entry, including duplicates, without losing any of them.
$names = $queue->map(fn (QueuedJob $job): string => $job->name);
echo "Names in queue order:\n";
foreach ($names->toArray() as $name) {
    echo "  {$name}\n";
}
echo "\n";

// filter() keeps every matching element — the three send-invoice jobs above
// priority 4 all survive, still as three separate entries.
$highPriority = $queue->filter(fn (QueuedJob $job): bool => $job->priority >= 4);
printf("filter() for priority >= 4 kept %d entries (all three send-invoice repeats):\n", $highPriority->length());
foreach ($highPriority->toArray() as $job) {
    printf("  %s (priority %d)\n", $job->name, $job->priority);
}
echo "\n";

// fold() can tally how many times each job name was actually queued, which
// is the whole point of keeping duplicates around in the first place.
$counts = $queue->fold([], function (array $tally, QueuedJob $job): array {
    $tally[$job->name] = ($tally[$job->name] ?? 0) + 1;

    return $tally;
});

echo "Run counts per job name:\n";
foreach ($counts as $name => $count) {
    echo "  {$name}: {$count}\n";
}
echo "\n";

// grouped() batches the queue into fixed-size chunks in order, e.g. to hand
// jobs to workers two at a time without reshuffling anything.
$batches = $queue->grouped(2);
echo "Dispatched in batches of 2:\n";
foreach ($batches->toArray() as $i => $batch) {
    $batchNames = implode(', ', array_map(fn (QueuedJob $job): string => $job->name, $batch->toArray()));
    printf("  batch %d: %s\n", $i, $batchNames);
}
