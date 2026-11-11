<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Quests\Duel\Duel;
use Tilttek\Quests\Exception\QuestRefused;
use Tilttek\Quests\Exception\UnknownQuest;
use Tilttek\Quests\Progress\QuestProgress;
use Tilttek\Quests\Schedule\Occurrence;

/**
 * The quests as the members see and decide them: a member accepts a quest — under a team, for a team quest — or
 * abandons it; reads where each quest stands for them and the quests they accomplished; and an account's data is
 * exported and erased.
 *
 * ```php
 * $quests->accept($member, 'winter-reading');
 * $quests->status($member, 'winter-reading')->progress->done(); // 2
 * ```
 *
 * Duels are sent and answered through `Duels`.
 */
final readonly class Quests
{
    public function __construct(
        private Evaluator $evaluator,
        private ClockInterface $clock,
    ) {
    }

    public function catalog(): Catalog
    {
        return $this->evaluator->catalog;
    }

    /**
     * The member accepts the quest, from now on — or takes up again a quest followed automatically they abandoned.
     * Accepting a quest followed already returns the member's enrolment as it is.
     *
     * @throws UnknownQuest
     * @throws QuestRefused when the quest is a duel, closed, locked, accomplished already in this occurrence, a team
     *                      quest without a team — or with another team than the member's —, or another quest with one
     */
    public function accept(ResourceReference $member, string $code, ?ResourceReference $team = null): Enrolment
    {
        $quest = $this->evaluator->catalog->get($code);
        $this->refuseDuel($quest);

        $subject = $this->subject($quest, $member, $team);
        $occurrence = $this->open($quest);
        $this->refuseCompleted($quest, $occurrence, $subject);

        if (!$this->evaluator->chain($quest, $subject) instanceof Window) {
            throw QuestRefused::because(\sprintf('"%s" opens once "%s" is accomplished.', $code, $quest->after ?? ''));
        }

        $enrolment = $this->evaluator->enrolments->find($code, $member);

        if ($enrolment instanceof Enrolment && !$enrolment->isAbandoned()) {
            if ($team instanceof ResourceReference && !$enrolment->isUnder($team)) {
                throw QuestRefused::because(\sprintf('The member follows "%s" with another team; they abandon it before joining another.', $code));
            }

            return $enrolment;
        }

        $enrolment = new Enrolment($code, $member, $this->clock->now(), $team);
        $this->evaluator->enrolments->save($enrolment);

        return $enrolment;
    }

    /**
     * The member abandons the quest: their activity from now on no longer counts for it. Abandoning it again returns
     * the enrolment as it is.
     *
     * @throws UnknownQuest
     * @throws QuestRefused when the quest is a duel, closed, accomplished in this occurrence, or never accepted
     */
    public function abandon(ResourceReference $member, string $code): Enrolment
    {
        $quest = $this->evaluator->catalog->get($code);
        $this->refuseDuel($quest);
        $occurrence = $this->open($quest);
        $subject = $this->evaluator->subjectOf($quest, $member);

        if ($subject instanceof ResourceReference) {
            $this->refuseCompleted($quest, $occurrence, $subject);
        }

        $enrolment = $this->evaluator->enrolments->find($code, $member);

        if (true === $enrolment?->isAbandoned()) {
            return $enrolment;
        }

        if (!$enrolment instanceof Enrolment && $quest->needsAcceptance()) {
            throw QuestRefused::because(\sprintf('The member does not follow "%s".', $code));
        }

        $now = $this->clock->now();
        $abandoned = ($enrolment ?? new Enrolment($code, $member, $now))->abandon($now);
        $this->evaluator->enrolments->save($abandoned);

        return $abandoned;
    }

    /**
     * Where the quest stands for the member, with the progress of its subject.
     *
     * @throws UnknownQuest
     */
    public function status(ResourceReference $member, string $code): QuestStatus
    {
        return $this->statusOf($member, $this->evaluator->catalog->get($code));
    }

    /**
     * Where every quest but the duels stands for the member, by code.
     *
     * @return list<QuestStatus>
     */
    public function board(ResourceReference $member): array
    {
        $statuses = [];

        foreach ($this->evaluator->catalog->all() as $quest) {
            if (QuestKind::Duel !== $quest->kind) {
                $statuses[] = $this->statusOf($member, $quest);
            }
        }

        return $statuses;
    }

    /**
     * The quests a member — or a team, or the community — accomplished, by moment.
     *
     * @return list<Completion>
     */
    public function completions(ResourceReference $subject): array
    {
        return $this->evaluator->completions->ofSubject($subject);
    }

    /**
     * What the quests keep of a member, for an access request: their contributions, enrolments, completions and duels.
     *
     * @return array{contributions: list<Contribution>, enrolments: list<Enrolment>, completions: list<Completion>, duels: list<Duel>}
     */
    public function export(ResourceReference $member): array
    {
        return [
            'contributions' => $this->evaluator->contributions->ofMember($member),
            'enrolments' => $this->evaluator->enrolments->ofMember($member),
            'completions' => $this->evaluator->completions->ofSubject($member),
            'duels' => $this->evaluator->duels->ofMember($member),
        ];
    }

    /**
     * Erases a member: their contributions, enrolments, the quests they accomplished themselves and their duels. What
     * a team or the community accomplished stays, its rewards given.
     */
    public function erase(ResourceReference $member): Erasure
    {
        return new Erasure(
            $this->evaluator->contributions->eraseMember($member),
            $this->evaluator->enrolments->eraseMember($member),
            $this->evaluator->completions->eraseSubject($member),
            $this->evaluator->duels->eraseMember($member),
        );
    }

    private function statusOf(ResourceReference $member, Quest $quest): QuestStatus
    {
        $occurrence = $quest->schedule->occurrenceAt($this->clock->now());

        $enrolment = $this->evaluator->enrolments->find($quest->code, $member);
        $subject = $this->evaluator->subjectOf($quest, $member);
        $team = QuestKind::Team === $quest->kind ? $subject : null;

        if (!$subject instanceof ResourceReference) {
            return new QuestStatus($quest, $occurrence instanceof Occurrence ? QuestState::Available : QuestState::Closed, QuestProgress::none($quest), $occurrence);
        }

        $completion = $occurrence instanceof Occurrence
            ? $this->evaluator->completions->find($quest->code, $occurrence->code, $subject)
            : $this->evaluator->completions->latest($quest->code, $subject);

        if ($completion instanceof Completion) {
            return new QuestStatus($quest, QuestState::Completed, $this->evaluator->progress($quest, $subject, $occurrence?->window() ?? new Window()), $occurrence, $completion, $team);
        }

        $chain = $this->evaluator->chain($quest, $subject);

        if (!$occurrence instanceof Occurrence || !$chain instanceof Window) {
            return new QuestStatus($quest, $occurrence instanceof Occurrence ? QuestState::Locked : QuestState::Closed, QuestProgress::none($quest), $occurrence, null, $team);
        }

        $state = match (true) {
            true === $enrolment?->isAbandoned() => QuestState::Abandoned,
            $quest->needsAcceptance() && !$enrolment instanceof Enrolment => QuestState::Available,
            default => QuestState::UnderWay,
        };

        return new QuestStatus($quest, $state, $this->evaluator->progress($quest, $subject, $occurrence->window()->intersect($chain)), $occurrence, null, $team);
    }

    /**
     * The subject the member's acceptance is about: their team for a team quest — which a team quest needs, and no
     * other quest takes —, the community, or the member.
     */
    private function subject(Quest $quest, ResourceReference $member, ?ResourceReference $team): ResourceReference
    {
        if ((QuestKind::Team === $quest->kind) !== $team instanceof ResourceReference) {
            throw QuestRefused::because(\sprintf('A team quest is joined under a team, and only a team quest; "%s" is a %s quest.', $quest->code, $quest->kind->value));
        }

        return $team ?? (QuestKind::Community === $quest->kind ? Completion::community($quest->code) : $member);
    }

    private function refuseDuel(Quest $quest): void
    {
        if (QuestKind::Duel === $quest->kind) {
            throw QuestRefused::because(\sprintf('"%s" is a duel: a member challenges another to it.', $quest->code));
        }
    }

    private function open(Quest $quest): Occurrence
    {
        return $quest->schedule->occurrenceAt($this->clock->now()) ?? throw QuestRefused::because(\sprintf('"%s" is closed.', $quest->code));
    }

    private function refuseCompleted(Quest $quest, Occurrence $occurrence, ResourceReference $subject): void
    {
        if ($this->evaluator->completions->find($quest->code, $occurrence->code, $subject) instanceof Completion) {
            throw QuestRefused::because(\sprintf('"%s" is accomplished already.', $quest->code));
        }
    }
}
