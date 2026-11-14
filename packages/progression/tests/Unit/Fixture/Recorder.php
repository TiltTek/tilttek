<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit\Fixture;

use Override;
use RuntimeException;
use Tilttek\Progression\LevelChange;
use Tilttek\Progression\ProgressionObserver;
use Tilttek\Progression\ReachedMilestone;
use Tilttek\Progression\Streak\StreakBroken;

/**
 * An observer that writes down what it is told, one line each; it fails as many times as it is told to.
 */
final class Recorder implements ProgressionObserver
{
    /**
     * @var list<string>
     */
    public array $told = [];

    public int $failures = 0;

    public function clear(): void
    {
        $this->told = [];
    }

    #[Override]
    public function leveledUp(LevelChange $change): void
    {
        $this->fail();
        $this->told[] = \sprintf('up %s %s %s %d→%d %s', $change->track, $change->subject->toString(), $change->season, $change->from, $change->to, $change->activity);
    }

    #[Override]
    public function leveledDown(LevelChange $change): void
    {
        $this->fail();
        $this->told[] = \sprintf('down %s %s %s %d→%d %s', $change->track, $change->subject->toString(), $change->season, $change->from, $change->to, $change->activity);
    }

    #[Override]
    public function milestoneReached(ReachedMilestone $reached): void
    {
        $this->fail();
        $this->told[] = \sprintf('milestone %s %s %s %s', $reached->track, $reached->milestone, $reached->subject->toString(), $reached->season);
    }

    #[Override]
    public function streakBroken(StreakBroken $broken): void
    {
        $this->fail();
        $this->told[] = \sprintf('broken %s %s %d %d %s', $broken->streak, $broken->member->toString(), $broken->length, $broken->lastActive, $broken->at->format('Y-m-d H:i'));
    }

    private function fail(): void
    {
        if ($this->failures > 0) {
            --$this->failures;

            throw new RuntimeException('The observer failed.');
        }
    }
}
