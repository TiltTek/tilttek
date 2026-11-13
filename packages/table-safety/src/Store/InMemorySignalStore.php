<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Store;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\Contract\SignalStore;
use Tilttek\TableSafety\Signal\Signal;

/**
 * The signals in memory, for tests and for a process that keeps nothing.
 */
final class InMemorySignalStore implements SignalStore
{
    /** @var array<string, array<string, Signal>> by table, then identifier, in the order they were added */
    private array $signals = [];

    #[Override]
    public function add(Signal $signal): void
    {
        $this->signals[$signal->table->toString()][$signal->id] = $signal;
    }

    #[Override]
    public function acknowledge(ResourceReference $table, string $id, DateTimeImmutable $at): ?Signal
    {
        $signal = $this->signals[$table->toString()][$id] ?? null;

        if (null === $signal || $signal->isAcknowledged()) {
            return null;
        }

        return $this->signals[$table->toString()][$id] = $signal->acknowledge($at);
    }

    #[Override]
    public function recent(ResourceReference $table, ?DateTimeImmutable $after = null, int $limit = 100): array
    {
        $signals = array_values(array_filter(
            $this->signals[$table->toString()] ?? [],
            static fn (Signal $signal): bool => !$after instanceof DateTimeImmutable || $signal->raisedAt > $after,
        ));
        usort($signals, static fn (Signal $a, Signal $b): int => $a->raisedAt <=> $b->raisedAt);

        return \array_slice($signals, -$limit);
    }

    #[Override]
    public function ofParticipant(ResourceReference $participant): array
    {
        $found = [];

        foreach ($this->signals as $signals) {
            foreach ($signals as $signal) {
                if ($signal->raisedBy?->equals($participant) ?? false) {
                    $found[] = $signal;
                }
            }
        }

        return $found;
    }

    #[Override]
    public function forgetParticipant(ResourceReference $participant): int
    {
        $forgotten = 0;

        foreach ($this->ofParticipant($participant) as $signal) {
            $this->signals[$signal->table->toString()][$signal->id] = $signal->anonymous();
            ++$forgotten;
        }

        return $forgotten;
    }

    #[Override]
    public function purge(DateTimeImmutable $before): int
    {
        $purged = 0;

        foreach ($this->signals as $table => $signals) {
            $kept = array_filter($signals, static fn (Signal $signal): bool => $signal->raisedAt >= $before);
            $purged += \count($signals) - \count($kept);
            $this->signals[$table] = $kept;
        }

        return $purged;
    }
}
