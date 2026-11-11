<?php

declare(strict_types=1);

namespace Tilttek\Progression;

use Tilttek\Progression\Streak\StreakBroken;

/**
 * Told when a subject levels up or down, reaches a milestone, or when a member's streak breaks: to notify them, to give
 * a milestone's reward, or to show it at once.
 */
interface ProgressionObserver
{
    public function leveledUp(LevelChange $change): void;

    public function leveledDown(LevelChange $change): void;

    public function milestoneReached(ReachedMilestone $reached): void;

    public function streakBroken(StreakBroken $broken): void;
}
