<?php

declare(strict_types=1);

namespace Tilttek\Quests\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Contribution;

/**
 * Where the contributions of the activity to the quests' steps are kept: one per quest, step and activity, written
 * once even when the journal hands the activity twice.
 */
interface ContributionStore
{
    /**
     * Keeps the contribution; false when the activity already brought one to this quest and step.
     */
    public function add(Contribution $contribution): bool;

    /**
     * Removes every contribution of the activity, for its cancellation, and returns them.
     *
     * @return list<Contribution>
     */
    public function remove(string $activity): array;

    /**
     * The contributions to a quest — of these members only, unless null —, from `from`, included, to `until`,
     * excluded, by moment, then activity, then step.
     *
     * @param list<ResourceReference>|null $members
     *
     * @return list<Contribution>
     */
    public function of(string $quest, ?array $members = null, ?DateTimeImmutable $from = null, ?DateTimeImmutable $until = null): array;

    /**
     * The contributions of a member to every quest, by moment, for an export.
     *
     * @return list<Contribution>
     */
    public function ofMember(ResourceReference $member): array;

    /**
     * Forgets the contributions of the activities from `since` on — all of them when `since` is null —, before a
     * rebuild.
     *
     * @return int how many were forgotten
     */
    public function forget(?DateTimeImmutable $since): int;

    /**
     * @return int how many contributions of the member were removed
     */
    public function eraseMember(ResourceReference $member): int;
}
