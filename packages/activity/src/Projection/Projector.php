<?php

declare(strict_types=1);

namespace Tilttek\Activity\Projection;

use DateTimeImmutable;
use Tilttek\Activity\Exception\InvalidActivity;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Log\ActivityLog;

/**
 * Hands each projection the entries of the journal it has not seen, and replays the journal into it when a rule
 * changes.
 *
 * The checkpoint moves after each batch, so a failure loses at most one batch, handed again on the next run. Entries
 * the projection's filter leaves out move the checkpoint too: a projection that reads one verb does not scan the
 * same thousand entries of another verb on every run.
 *
 * Two runs of the same projection at the same time would apply the same entries twice: the application runs one at a
 * time, by a lock or a single worker.
 */
final readonly class Projector
{
    public function __construct(
        private ActivityLog $log,
        private Checkpoints $checkpoints,
        private int $batchSize = 500,
    ) {
        if ($batchSize < 1) {
            throw InvalidActivity::because(\sprintf('A batch holds at least one entry; %d is not a batch size.', $batchSize));
        }
    }

    /**
     * Hands the projection the entries after its checkpoint.
     *
     * @return int how many entries it applied
     */
    public function catchUp(Projection $projection): int
    {
        return $this->run($projection, $projection->filter(), $this->checkpoints->position($projection->name()));
    }

    /**
     * Resets the projection from `since` on — entirely when `since` is null — and hands it the activities of that
     * period again, from the start of the journal.
     *
     * @return int how many entries it applied
     */
    public function rebuild(Projection $projection, ?DateTimeImmutable $since = null): int
    {
        $projection->reset($since);
        $filter = $projection->filter();

        // The later of the two starts: a projection that reads from a date on never reads earlier.
        if ($since instanceof DateTimeImmutable) {
            $filter = $filter->since(max($filter->since ?? $since, $since));
        }

        return $this->run($projection, $filter, 0);
    }

    /**
     * How many entries were written after the projection's checkpoint, whether its filter reads them or not.
     */
    public function lag(Projection $projection): int
    {
        return max(0, $this->log->lastPosition() - $this->checkpoints->position($projection->name()));
    }

    private function run(Projection $projection, Filter $filter, int $after): int
    {
        $name = $projection->name();
        $end = $this->log->lastPosition();
        $applied = 0;

        do {
            $entries = $this->log->read($after, $this->batchSize, $filter);

            foreach ($entries as $entry) {
                $projection->apply($entry);
                $after = $entry->position;
                ++$applied;
            }

            // A short batch is the last: the checkpoint then moves to the end of the journal as it was when the run
            // began, past the entries the filter left out.
            $full = \count($entries) === $this->batchSize;
            $this->checkpoints->save($name, $full ? $after : max($after, $end));
        } while ($full);

        return $applied;
    }
}
