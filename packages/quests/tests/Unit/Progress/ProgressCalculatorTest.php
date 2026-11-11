<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit\Progress;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Quests\Contribution;
use Tilttek\Quests\Progress\Finish;
use Tilttek\Quests\Progress\ProgressCalculator;
use Tilttek\Quests\Progress\QuestProgress;
use Tilttek\Quests\Progress\StepProgress;
use Tilttek\Quests\Quest;
use Tilttek\Quests\Scope;
use Tilttek\Quests\Step;

#[CoversClass(ProgressCalculator::class)]
#[CoversClass(QuestProgress::class)]
#[CoversClass(StepProgress::class)]
#[CoversClass(Finish::class)]
final class ProgressCalculatorTest extends TestCase
{
    public function testFreeStepsAreDoneInAnyOrderAndTheQuestWithTheLastOfThem(): void
    {
        $quest = $this->quest(false);
        $progress = ProgressCalculator::calculate($quest, [
            $this->contribution('b', 'member:2', 'k1', '10:00', 3),
            $this->contribution('a', 'member:1', 'k2', '11:00'),
            $this->contribution('b', 'member:1', 'k3', '12:00', 4),
            $this->contribution('a', 'member:3', 'k4', '13:00'),
            $this->contribution('a', 'member:3', 'k5', '14:00'),
        ]);

        self::assertEquals(new Finish($this->at('13:00'), 'k4'), $progress->finish);
        self::assertTrue($progress->isComplete());
        self::assertSame(2, $progress->done());
        self::assertSame(['member:1', 'member:2', 'member:3'], $progress->members);
        self::assertSame([3, 7], array_map(static fn (StepProgress $step): int => $step->value, $progress->steps));
        self::assertSame([2, 5], array_map(static fn (StepProgress $step): int => $step->current(), $progress->steps));
        self::assertEquals([$this->at('13:00'), $this->at('12:00')], array_map(static fn (StepProgress $step): ?DateTimeImmutable => $step->doneAt, $progress->steps));
    }

    public function testTheLastStepDoneGivesTheMomentEvenWhenItIsTheFirstStep(): void
    {
        $progress = ProgressCalculator::calculate($this->quest(false), [
            $this->contribution('b', 'member:1', 'k1', '10:00', 5),
            $this->contribution('a', 'member:1', 'k2', '11:00'),
            $this->contribution('a', 'member:1', 'k3', '12:00'),
        ]);

        self::assertEquals(new Finish($this->at('12:00'), 'k3'), $progress->finish);
    }

    public function testStepsDoneAtTheSameMomentFinishWithTheLastStep(): void
    {
        $progress = ProgressCalculator::calculate(self::quest(false), [
            self::contribution('a', 'member:1', 'k1', '10:00', 2),
            self::contribution('b', 'member:1', 'k2', '10:00', 5),
        ]);

        self::assertEquals(new Finish(self::at('10:00'), 'k2'), $progress->finish);
    }

    public function testOrderedStepsCountFromTheMomentTheStepBeforeWasDone(): void
    {
        $quest = $this->quest(true);
        $progress = ProgressCalculator::calculate($quest, [
            $this->contribution('b', 'member:1', 'k1', '09:00', 5),
            $this->contribution('a', 'member:1', 'k2', '10:00'),
            $this->contribution('a', 'member:1', 'k3', '11:00'),
            $this->contribution('b', 'member:1', 'k3', '11:00', 2),
            $this->contribution('b', 'member:1', 'k4', '12:00', 2),
        ]);

        self::assertSame([2, 4], array_map(static fn (StepProgress $step): int => $step->value, $progress->steps));
        self::assertFalse($progress->isComplete());
        self::assertNull($progress->finish);
        self::assertSame(1, $progress->done());
        self::assertSame(['member:1'], $progress->members);

        $later = ProgressCalculator::calculate($quest, [
            $this->contribution('a', 'member:1', 'k2', '10:00'),
            $this->contribution('b', 'member:2', 'k3', '10:30', 9),
            $this->contribution('a', 'member:1', 'k4', '11:00'),
        ]);
        self::assertSame([2, 0], array_map(static fn (StepProgress $step): int => $step->value, $later->steps));
        self::assertSame(['member:1'], $later->members);
    }

    public function testAStepWhoseStepBeforeIsNotDoneCountsNothing(): void
    {
        $progress = ProgressCalculator::calculate($this->quest(true), [$this->contribution('b', 'member:1', 'k1', '09:00', 5)]);

        self::assertSame([0, 0], array_map(static fn (StepProgress $step): int => $step->value, $progress->steps));
        self::assertSame([], $progress->members);
        self::assertEquals(QuestProgress::none($this->quest(true)), $progress);
    }

    public function testAStepTellsHowFarItIs(): void
    {
        $step = new Step('s', new Scope(['a.b']), 3);

        self::assertSame(0, new StepProgress($step)->percent());
        self::assertSame(66, new StepProgress($step, 2)->percent());
        self::assertSame(100, new StepProgress($step, 7, $this->at('10:00'))->percent());
        self::assertFalse(new StepProgress($step, 2)->isDone());
    }

    private function quest(bool $ordered): Quest
    {
        return new Quest('q', [new Step('a', new Scope(['a.b']), 2), new Step('b', new Scope(['c.d']), 5)], ordered: $ordered);
    }

    private function contribution(string $step, string $member, string $activity, string $at, int $value = 1): Contribution
    {
        return new Contribution('q', $step, ResourceReference::fromString($member), $activity, $value, $this->at($at));
    }

    private function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-01 '.$time.' UTC');
    }
}
