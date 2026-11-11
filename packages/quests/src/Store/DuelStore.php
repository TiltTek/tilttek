<?php

declare(strict_types=1);

namespace Tilttek\Quests\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Duel\Duel;

/**
 * Where the duels are kept, by key.
 */
interface DuelStore
{
    /**
     * Keeps the duel, in place of the one under its key.
     */
    public function save(Duel $duel): void;

    public function find(string $key): ?Duel;

    /**
     * The accepted duels of a quest a member is a side of, by key.
     *
     * @return list<Duel>
     */
    public function accepted(string $quest, ResourceReference $member): array;

    /**
     * The duels a member challenged or was challenged to, by moment of the offer.
     *
     * @return list<Duel>
     */
    public function ofMember(ResourceReference $member): array;

    /**
     * The duels whose time is up: offered at or before `offeredBefore` and still unanswered, or accepted and ended by
     * `now`, by key.
     *
     * @return list<Duel>
     */
    public function due(DateTimeImmutable $offeredBefore, DateTimeImmutable $now): array;

    /**
     * Accepts again the duels won from `since` on — all of them when `since` is null —, before a rebuild replays the
     * activity that decided them, and returns them as they were.
     *
     * @return list<Duel>
     */
    public function reopen(?DateTimeImmutable $since): array;

    /**
     * @return int how many duels of the member were removed
     */
    public function eraseMember(ResourceReference $member): int;
}
