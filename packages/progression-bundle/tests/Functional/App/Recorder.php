<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Tests\Functional\App;

use Tilttek\Progression\ReachedMilestone;
use Tilttek\Progression\Streak\StreakBroken;
use Tilttek\ProgressionBundle\Infrastructure\Event\LevelDown;
use Tilttek\ProgressionBundle\Infrastructure\Event\LevelUp;

/**
 * A listener that writes down the events the bundle dispatches, one line each.
 */
final class Recorder
{
    /**
     * @var list<string>
     */
    public array $events = [];

    /**
     * @return list<string>
     */
    public function events(): array
    {
        return $this->events;
    }

    public function up(LevelUp $event): void
    {
        $this->events[] = \sprintf('up %s %d→%d', $event->change->track, $event->change->from, $event->change->to);
    }

    public function down(LevelDown $event): void
    {
        $this->events[] = \sprintf('down %s %d→%d', $event->change->track, $event->change->from, $event->change->to);
    }

    public function milestone(ReachedMilestone $reached): void
    {
        $this->events[] = 'milestone '.$reached->milestone;
    }

    public function broken(StreakBroken $broken): void
    {
        $this->events[] = 'broken '.$broken->streak;
    }
}
