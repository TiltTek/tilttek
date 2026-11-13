<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Store;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\Contract\ParticipationStore;
use Tilttek\TableSafety\Participation;

/**
 * The participations in memory, for tests and for a process that keeps nothing. A table's participations are listed
 * in the order of their participants' references, not of their answers.
 */
final class InMemoryParticipationStore implements ParticipationStore
{
    /** @var array<string, array<string, Participation>> by table, then participant */
    private array $participations = [];

    #[Override]
    public function find(ResourceReference $table, ResourceReference $participant): ?Participation
    {
        return $this->participations[$table->toString()][$participant->toString()] ?? null;
    }

    #[Override]
    public function save(Participation $participation): void
    {
        $this->participations[$participation->table->toString()][$participation->participant->toString()] = $participation;
    }

    #[Override]
    public function remove(ResourceReference $table, ResourceReference $participant): void
    {
        unset($this->participations[$table->toString()][$participant->toString()]);
    }

    #[Override]
    public function ofTable(ResourceReference $table): array
    {
        $participations = $this->participations[$table->toString()] ?? [];
        ksort($participations, \SORT_STRING);

        return array_values($participations);
    }

    #[Override]
    public function ofParticipant(ResourceReference $participant): array
    {
        $found = [];

        foreach ($this->participations as $participations) {
            if (isset($participations[$participant->toString()])) {
                $found[] = $participations[$participant->toString()];
            }
        }

        return $found;
    }

    #[Override]
    public function eraseParticipant(ResourceReference $participant): int
    {
        $erased = 0;

        foreach ($this->participations as $table => $participations) {
            if (isset($participations[$participant->toString()])) {
                unset($this->participations[$table][$participant->toString()]);
                ++$erased;
            }
        }

        return $erased;
    }
}
