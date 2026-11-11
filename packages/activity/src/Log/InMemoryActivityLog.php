<?php

declare(strict_types=1);

namespace Tilttek\Activity\Log;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Appended;
use Tilttek\Activity\Entry;
use Tilttek\Activity\EntryKind;
use Tilttek\Activity\Erasure;
use Tilttek\Activity\Exception\IdempotencyConflict;
use Tilttek\Activity\Filter;
use Tilttek\Activity\IdempotencyKey;
use Tilttek\Activity\Totals;

/**
 * The journal in memory, for tests and for a process that keeps nothing: what it holds is gone with it.
 */
final class InMemoryActivityLog implements ActivityLog
{
    /**
     * @var array<int, Entry>
     */
    private array $entries = [];

    /**
     * @var array<string, array<value-of<EntryKind>, int>>
     */
    private array $positions = [];

    private int $last = 0;

    #[Override]
    public function append(Activity $activity, DateTimeImmutable $at): Appended
    {
        $existing = $this->entry($activity->key, EntryKind::Recorded);

        if ($existing instanceof Entry) {
            if (!$existing->activity->sameGestureAs($activity)) {
                throw IdempotencyConflict::on($activity->key);
            }

            return new Appended($existing, false);
        }

        return new Appended($this->write(EntryKind::Recorded, $activity, $at), true);
    }

    #[Override]
    public function cancel(IdempotencyKey $key, DateTimeImmutable $at): ?Appended
    {
        $recorded = $this->entry($key, EntryKind::Recorded);

        if (!$recorded instanceof Entry) {
            return null;
        }

        $existing = $this->entry($key, EntryKind::Cancelled);

        if ($existing instanceof Entry) {
            return new Appended($existing, false);
        }

        return new Appended($this->write(EntryKind::Cancelled, $recorded->activity, $at), true);
    }

    #[Override]
    public function find(IdempotencyKey $key): ?Entry
    {
        return $this->entry($key, EntryKind::Recorded);
    }

    #[Override]
    public function isCancelled(IdempotencyKey $key): bool
    {
        return $this->entry($key, EntryKind::Cancelled) instanceof Entry;
    }

    #[Override]
    public function read(int $after, int $limit, Filter $filter): array
    {
        Reading::check($after, $limit);
        $read = [];

        foreach ($this->entries as $position => $entry) {
            if ($position > $after && $filter->matches($entry->activity)) {
                $read[] = $entry;

                if (\count($read) === $limit) {
                    break;
                }
            }
        }

        return $read;
    }

    #[Override]
    public function lastPosition(): int
    {
        return array_key_last($this->entries) ?? 0;
    }

    #[Override]
    public function totals(Filter $filter): Totals
    {
        $count = 0;
        $quantity = 0;

        foreach ($this->entries as $entry) {
            if (!$entry->isCancellation() && $filter->matches($entry->activity) && !$this->isCancelled($entry->activity->key)) {
                ++$count;
                $quantity += $entry->activity->quantity;
            }
        }

        return new Totals($count, $quantity);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): Erasure
    {
        $deleted = 0;
        $detached = 0;

        foreach ($this->entries as $position => $entry) {
            if ($entry->activity->member->equals($member)) {
                unset($this->entries[$position], $this->positions[$entry->activity->key->value]);
                ++$deleted;
            } elseif ($entry->activity->object?->toString() === $member->toString()) {
                $this->entries[$position] = new Entry($position, $entry->kind, $entry->activity->withoutObject(), $entry->at);
                ++$detached;
            }
        }

        return new Erasure($deleted, $detached);
    }

    /**
     * Empties the journal: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->entries = [];
        $this->positions = [];
        $this->last = 0;
    }

    private function entry(IdempotencyKey $key, EntryKind $kind): ?Entry
    {
        $position = $this->positions[$key->value][$kind->value] ?? null;

        return null === $position ? null : $this->entries[$position];
    }

    private function write(EntryKind $kind, Activity $activity, DateTimeImmutable $at): Entry
    {
        $entry = new Entry(++$this->last, $kind, $activity, $at);
        $this->entries[$entry->position] = $entry;
        $this->positions[$activity->key->value][$kind->value] = $entry->position;

        return $entry;
    }
}
