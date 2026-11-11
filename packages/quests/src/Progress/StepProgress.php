<?php

declare(strict_types=1);

namespace Tilttek\Quests\Progress;

use DateTimeImmutable;
use Tilttek\Quests\Step;

/**
 * How far a step is: what counted towards it, its target, and when it was done, if it was.
 */
final readonly class StepProgress
{
    public function __construct(
        public Step $step,
        public int $value = 0,
        public ?DateTimeImmutable $doneAt = null,
    ) {
    }

    public function isDone(): bool
    {
        return $this->doneAt instanceof DateTimeImmutable;
    }

    /**
     * What is counted, up to the target.
     */
    public function current(): int
    {
        return min($this->value, $this->step->target);
    }

    public function percent(): int
    {
        return intdiv($this->current() * 100, $this->step->target);
    }
}
