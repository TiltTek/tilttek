<?php

declare(strict_types=1);

namespace Tilttek\Badges\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Badges\Contribution;

/**
 * Where the contributions of the activity to the badges' criteria are kept: one per badge, path and activity, written
 * once even when the journal hands the activity twice.
 */
interface ProgressStore
{
    /**
     * Keeps the contribution; false when the activity already brought one to this badge and path.
     */
    public function add(Contribution $contribution): bool;

    /**
     * Removes every contribution of the activity, for its cancellation, and returns them.
     *
     * @return list<Contribution>
     */
    public function remove(string $activity): array;

    public function count(string $badge, string $path, ResourceReference $member): int;

    public function quantity(string $badge, string $path, ResourceReference $member): int;

    /**
     * @return list<DateTimeImmutable>
     */
    public function moments(string $badge, string $path, ResourceReference $member): array;

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
