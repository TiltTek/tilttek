<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals\Event;

use Phil\Resource\ResourceReference;
use Tilttek\WritingGoals\Day;

/**
 * Dispatched when a count logged brings a goal to its target: the application may award a badge or start a
 * celebration. It carries references only. Correcting a day below the target and back above it dispatches it again,
 * as can two counts logged at once: a listener that grants something keeps it once by `idempotencyKey()`.
 */
final readonly class GoalReached
{
    public function __construct(
        public string $goal,
        public ResourceReference $author,
        public Day $day,
    ) {
    }

    /**
     * One key per goal, whatever the day it was reached on.
     */
    public function idempotencyKey(): string
    {
        return \sprintf('writing-goal:%s', $this->goal);
    }
}
