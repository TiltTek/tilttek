<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\League\League;
use Tilttek\Leaderboards\League\Membership;
use Tilttek\Leaderboards\League\Settlement;

/**
 * Where the leagues are kept: each member's tier on each board, their membership of a division each week they took
 * part, and the weeks settled.
 *
 * Memberships are listed by tier name, by division, then in their order of arrival — the moment they joined, then the
 * member's reference.
 */
interface LeagueStore
{
    /**
     * The member's tier on the board, null when they never took part in a settled week.
     */
    public function tier(string $board, ResourceReference $member): ?string;

    public function membership(string $board, string $week, ResourceReference $member): ?Membership;

    /**
     * Places the member in a division of the tier for the week: the lowest numbered of the cohort that holds fewer than
     * `size` members, or the number after the highest of the tier when all are full. A member already placed that week
     * keeps the membership they have, which is returned.
     */
    public function join(string $board, string $week, ResourceReference $member, string $tier, DateTimeImmutable $at, int $size, int $cohort = League::NO_COHORT): Membership;

    /**
     * The memberships of one division.
     *
     * @return list<Membership>
     */
    public function division(string $board, string $week, string $tier, int $division): array;

    /**
     * Every membership of the board's week.
     *
     * @return list<Membership>
     */
    public function memberships(string $board, string $week): array;

    public function isSettled(string $board, string $week): bool;

    /**
     * The weeks of the board that have memberships and are not settled, from the earliest code.
     *
     * @return list<string>
     */
    public function unsettled(string $board): array;

    /**
     * Settles the week once: writes each membership's movement and each member's new tier — by `kind:identifier` —
     * and marks the week settled, all together. False, writing nothing, when the week is already settled.
     *
     * @param array<string, string> $tiers
     */
    public function settle(Settlement $settlement, array $tiers): bool;

    /**
     * The member's tiers by board, for an export.
     *
     * @return array<string, string>
     */
    public function tiersOf(ResourceReference $member): array;

    /**
     * The member's memberships, by board and week, for an export.
     *
     * @return list<Membership>
     */
    public function membershipsOf(ResourceReference $member): array;

    /**
     * Removes the member's tiers and memberships; the others keep their divisions.
     *
     * @return int how many rows were removed
     */
    public function eraseMember(ResourceReference $member): int;
}
