<?php

declare(strict_types=1);

namespace Tilttek\Quests\Progress;

use Tilttek\Quests\Quest;
use Tilttek\Quests\Step;

/**
 * How far a subject is with a quest: each step's progress, when the last step needed was done and by which activity,
 * and the members whose activity counted.
 */
final readonly class QuestProgress
{
    /**
     * @param list<StepProgress> $steps
     * @param list<string>       $members the members whose activity counted, as `kind:identifier`
     */
    public function __construct(
        public Quest $quest,
        public array $steps,
        public ?Finish $finish = null,
        public array $members = [],
    ) {
    }

    /**
     * A quest nobody advanced yet.
     */
    public static function none(Quest $quest): self
    {
        return new self($quest, array_map(static fn (Step $step): StepProgress => new StepProgress($step), $quest->steps));
    }

    public function isComplete(): bool
    {
        return $this->finish instanceof Finish;
    }

    /**
     * How many steps are done.
     */
    public function done(): int
    {
        return \count(array_filter($this->steps, static fn (StepProgress $step): bool => $step->isDone()));
    }
}
