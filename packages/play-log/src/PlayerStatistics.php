<?php

declare(strict_types=1);

namespace Tilttek\PlayLog;

/**
 * A member seen from the plays read: the plays they shared with the member asked about, or played in the group, and
 * how many of them they won.
 */
final readonly class PlayerStatistics
{
    public function __construct(public string $member, public int $plays, public int $wins)
    {
    }
}
