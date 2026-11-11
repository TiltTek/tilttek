<?php

declare(strict_types=1);

namespace Tilttek\Badges\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Badges\Award;

/**
 * Where the members' awards are kept: one per badge, member and number.
 */
interface AwardStore
{
    /**
     * Keeps the award; false when the member already holds this number of the badge.
     */
    public function add(Award $award): bool;

    public function remove(Award $award): void;

    /**
     * The member's awards — of one badge when it is named —, by the moment they were earned, then badge and number.
     *
     * @return list<Award>
     */
    public function of(ResourceReference $member, ?string $badge = null): array;

    /**
     * Forgets the awards derived from the activity — not those awarded by hand — earned from `since` on, all of them
     * when `since` is null, before a rebuild; returns them.
     *
     * @return list<Award>
     */
    public function forgetDerived(?DateTimeImmutable $since): array;

    /**
     * @return int how many awards of the member were removed
     */
    public function eraseMember(ResourceReference $member): int;
}
