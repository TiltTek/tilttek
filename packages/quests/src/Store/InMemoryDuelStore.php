<?php

declare(strict_types=1);

namespace Tilttek\Quests\Store;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Duel\Duel;
use Tilttek\Quests\Duel\DuelStatus;

/**
 * The duels in memory, forgotten with the process: for tests, scripts and examples.
 */
final class InMemoryDuelStore implements DuelStore
{
    /**
     * @var array<string, Duel>
     */
    private array $duels = [];

    #[Override]
    public function save(Duel $duel): void
    {
        $this->duels[$duel->key] = $duel;
        ksort($this->duels, \SORT_STRING);
    }

    #[Override]
    public function find(string $key): ?Duel
    {
        return $this->duels[$key] ?? null;
    }

    #[Override]
    public function accepted(string $quest, ResourceReference $member): array
    {
        return array_values(array_filter($this->duels, static fn (Duel $duel): bool => DuelStatus::Accepted === $duel->status && $duel->quest === $quest && $duel->involves($member)));
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        $duels = array_filter($this->duels, static fn (Duel $duel): bool => $duel->involves($member));
        usort($duels, static fn (Duel $a, Duel $b): int => [$a->offeredAt->getTimestamp(), $a->key] <=> [$b->offeredAt->getTimestamp(), $b->key]);

        return $duels;
    }

    #[Override]
    public function due(DateTimeImmutable $offeredBefore, DateTimeImmutable $now): array
    {
        return array_values(array_filter(
            $this->duels,
            static fn (Duel $duel): bool => (DuelStatus::Offered === $duel->status && $duel->offeredAt <= $offeredBefore)
                || (DuelStatus::Accepted === $duel->status && $duel->endsAt instanceof DateTimeImmutable && $duel->endsAt <= $now),
        ));
    }

    #[Override]
    public function reopen(?DateTimeImmutable $since): array
    {
        $won = array_filter(
            $this->duels,
            static fn (Duel $duel): bool => DuelStatus::Won === $duel->status && (!$since instanceof DateTimeImmutable || $duel->decidedAt >= $since),
        );

        foreach ($won as $key => $duel) {
            $this->duels[$key] = $duel->reopened();
        }

        return array_values($won);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $count = \count($this->duels);
        $this->duels = array_filter($this->duels, static fn (Duel $duel): bool => !$duel->involves($member));

        return $count - \count($this->duels);
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->duels = [];
    }
}
