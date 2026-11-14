<?php

declare(strict_types=1);

namespace Tilttek\Badges\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Badges\Award;

/**
 * Where the members' awards are kept: one per badge, member and number, with the notice the observer is still owed —
 * that of an award earned by the activity, until it is given, or that of an award withdrawn, which stays until its
 * notice is given and it is removed.
 */
interface AwardStore
{
    /**
     * Keeps the award; false when the member already holds this number of the badge, or a withdrawn award of this
     * number still waits for its notice. An award earned by the activity is kept with its notice due, until
     * `markDelivered()`; an award made by hand is owed no notice.
     */
    public function add(Award $award): bool;

    /**
     * Notes that the notice of the award was given; a withdrawn award keeps the notice of its withdrawal due.
     */
    public function markDelivered(Award $award): void;

    /**
     * The member's awards whose notice is due, by badge and number.
     *
     * @return list<Award>
     */
    public function undelivered(ResourceReference $member): array;

    /**
     * Withdraws the award the member holds: no longer held, it waits for the notice of its withdrawal, then for
     * `remove()`; true when that notice is due, false when the member does not hold it. The change is one atomic step,
     * whether or not the award's own notice was given: that notice may be under way in another process, so a withdrawn
     * award is always owed the notice of its withdrawal, and `markDelivered()` leaves that notice due.
     */
    public function withdraw(Award $award): bool;

    /**
     * The member's withdrawn awards whose notice is due, by badge and number.
     *
     * @return list<Award>
     */
    public function withdrawn(ResourceReference $member): array;

    /**
     * Removes the award, held or withdrawn, with the notice it was owed.
     */
    public function remove(Award $award): void;

    /**
     * The awards the member holds — of one badge when it is named —, by the moment they were earned, then badge and
     * number; the withdrawn awards are not held.
     *
     * @return list<Award>
     */
    public function of(ResourceReference $member, ?string $badge = null): array;

    /**
     * Forgets the awards derived from the activity — not those awarded by hand — earned from `since` on, all of them
     * when `since` is null, before a rebuild, held or withdrawn; returns those held whose notice was given.
     *
     * @return list<Award>
     */
    public function forgetDerived(?DateTimeImmutable $since): array;

    /**
     * @return int how many awards of the member were removed, held or withdrawn
     */
    public function eraseMember(ResourceReference $member): int;
}
