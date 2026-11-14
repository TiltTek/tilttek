<?php

declare(strict_types=1);

namespace Tilttek\Badges;

/**
 * Told when a member earns a badge or loses one, to notify them or to show it at once.
 *
 * Told at least once: a notice the observer failed on is given again, so the same award can be told twice. An
 * observer is idempotent: it recognises an award by its badge, member and number, and does nothing for a notice it
 * has already acted on.
 */
interface AwardObserver
{
    public function awarded(Award $award, Badge $badge): void;

    public function revoked(Award $award, Badge $badge): void;
}
