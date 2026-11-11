<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Infrastructure\Event;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Progression\LevelChange;
use Tilttek\Progression\ProgressionObserver;
use Tilttek\Progression\ReachedMilestone;
use Tilttek\Progression\Streak\StreakBroken;

/**
 * Turns what the projection and the sweep tell into events of the application's event dispatcher: `LevelUp` and
 * `LevelDown` around the level change, the `ReachedMilestone` and the `StreakBroken` of the library as they are.
 */
final readonly class DispatchingObserver implements ProgressionObserver
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    #[Override]
    public function leveledUp(LevelChange $change): void
    {
        $this->dispatcher->dispatch(new LevelUp($change));
    }

    #[Override]
    public function leveledDown(LevelChange $change): void
    {
        $this->dispatcher->dispatch(new LevelDown($change));
    }

    #[Override]
    public function milestoneReached(ReachedMilestone $reached): void
    {
        $this->dispatcher->dispatch($reached);
    }

    #[Override]
    public function streakBroken(StreakBroken $broken): void
    {
        $this->dispatcher->dispatch($broken);
    }
}
