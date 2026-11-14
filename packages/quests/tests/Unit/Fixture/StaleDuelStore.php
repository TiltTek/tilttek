<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Duel\Duel;
use Tilttek\Quests\Duel\DuelStatus;
use Tilttek\Quests\Store\DuelStore;
use Tilttek\Quests\Store\InMemoryDuelStore;

/**
 * Duels in memory whose reads, once frozen, answer what was kept at that moment while the writes go on: a request
 * that read a duel before another request answered it. A transition refused thaws them: the request then reads the
 * duel as it is.
 */
final class StaleDuelStore implements DuelStore
{
    public readonly InMemoryDuelStore $current;

    private ?InMemoryDuelStore $then = null;

    public function __construct()
    {
        $this->current = new InMemoryDuelStore();
    }

    /**
     * From now on, the reads answer the duels as they are now.
     */
    public function freeze(): void
    {
        $this->then = clone $this->current;
    }

    #[Override]
    public function save(Duel $duel): void
    {
        $this->current->save($duel);
    }

    #[Override]
    public function transition(Duel $duel, DuelStatus $from): bool
    {
        if ($this->current->transition($duel, $from)) {
            return true;
        }

        $this->then = null;

        return false;
    }

    #[Override]
    public function find(string $key): ?Duel
    {
        return $this->reads()->find($key);
    }

    #[Override]
    public function accepted(string $quest, ResourceReference $member): array
    {
        return $this->reads()->accepted($quest, $member);
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return $this->reads()->ofMember($member);
    }

    #[Override]
    public function due(DateTimeImmutable $offeredBefore, DateTimeImmutable $now): array
    {
        return $this->reads()->due($offeredBefore, $now);
    }

    #[Override]
    public function reopen(?DateTimeImmutable $since): array
    {
        return $this->current->reopen($since);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return $this->current->eraseMember($member);
    }

    private function reads(): InMemoryDuelStore
    {
        return $this->then ?? $this->current;
    }
}
