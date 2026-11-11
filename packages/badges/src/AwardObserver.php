<?php

declare(strict_types=1);

namespace Tilttek\Badges;

/**
 * Told when a member earns a badge or loses one, to notify them or to show it at once.
 */
interface AwardObserver
{
    public function awarded(Award $award, Badge $badge): void;

    public function revoked(Award $award, Badge $badge): void;
}
