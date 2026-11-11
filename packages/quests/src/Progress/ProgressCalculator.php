<?php

declare(strict_types=1);

namespace Tilttek\Quests\Progress;

use DateTimeImmutable;
use Tilttek\Quests\Contribution;
use Tilttek\Quests\Quest;

/**
 * Reads a quest's progress from the contributions that count for a subject. A step is done at the moment of the
 * contribution that reaches its target; for ordered steps, a contribution counts for a step only from the moment the
 * step before it was done, and a step whose previous one is not done counts nothing. The quest is accomplished when
 * every step is done, at the moment of the last of them.
 */
final class ProgressCalculator
{
    private function __construct()
    {
    }

    /**
     * @param array<Contribution> $contributions those that count, by moment, then activity
     */
    public static function calculate(Quest $quest, array $contributions): QuestProgress
    {
        $steps = [];
        $members = [];
        $finish = null;
        $previous = null;
        $blocked = false;

        foreach ($quest->steps as $step) {
            $value = 0;
            $doneAt = null;

            foreach ($contributions as $contribution) {
                if ($blocked || $contribution->step !== $step->code || ($previous instanceof DateTimeImmutable && $contribution->occurredAt < $previous)) {
                    continue;
                }

                $value += $contribution->value;
                $members[] = $contribution->member->toString();

                if (null === $doneAt && $value >= $step->target) {
                    $doneAt = $contribution->occurredAt;

                    if (!$finish instanceof Finish || $doneAt >= $finish->at) {
                        $finish = new Finish($doneAt, $contribution->activity);
                    }
                }
            }

            $steps[] = new StepProgress($step, $value, $doneAt);

            if ($quest->ordered) {
                $previous = $doneAt;
                $blocked = !$doneAt instanceof DateTimeImmutable;
            }
        }

        $complete = array_all($steps, static fn (StepProgress $step): bool => $step->isDone());
        $names = array_unique($members);
        sort($names, \SORT_STRING);

        return new QuestProgress($quest, $steps, $complete ? $finish : null, $names);
    }
}
