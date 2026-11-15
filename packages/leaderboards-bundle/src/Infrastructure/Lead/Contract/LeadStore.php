<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Lead\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Lead;

/**
 * Where the leads taken are kept, so that a member is told once per board and period that they took the lead, even
 * when the projection counts the same activities again.
 */
interface LeadStore
{
    /**
     * Keeps the lead; false when the member already took the lead of that board in that period, or when another
     * process kept it at the same moment.
     */
    public function claim(Lead $lead): bool;

    /**
     * Whether the member took the lead of the board in the period.
     */
    public function has(string $board, string $period, ResourceReference $member): bool;

    /**
     * The leads the member took, by board and period.
     *
     * @return list<Lead>
     */
    public function leads(ResourceReference $member): array;

    /**
     * Forgets the member's leads, and returns how many there were.
     */
    public function eraseMember(ResourceReference $member): int;
}
