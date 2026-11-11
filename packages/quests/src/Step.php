<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use Tilttek\Activity\Activity;
use Tilttek\Quests\Exception\InvalidQuest;

/**
 * One step of a quest: the activities it reads and how many of them — or how much of their quantity — it asks for.
 * A step asking for one activity is a first time: *publish a first sheet*.
 */
final readonly class Step
{
    public const int MAX_TARGET = 1_000_000_000_000;

    public function __construct(
        public string $code,
        public Scope $scope,
        public int $target = 1,
        public StepMeasure $measure = StepMeasure::Count,
    ) {
        Names::check($code, 'step');

        if ($target < 1 || $target > self::MAX_TARGET) {
            throw InvalidQuest::because(\sprintf('A step asks for 1 to %d; "%s" asks for %d.', self::MAX_TARGET, $code, $target));
        }
    }

    public function accepts(Activity $activity): bool
    {
        return $this->scope->accepts($activity);
    }

    /**
     * What an accepted activity brings to the step: one, or its quantity for a total.
     */
    public function valueOf(Activity $activity): int
    {
        return StepMeasure::Total === $this->measure ? $activity->quantity : 1;
    }
}
