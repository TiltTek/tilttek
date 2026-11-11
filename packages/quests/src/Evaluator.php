<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Duel\Duel;
use Tilttek\Quests\Progress\ProgressCalculator;
use Tilttek\Quests\Progress\QuestProgress;
use Tilttek\Quests\Store\CompletionStore;
use Tilttek\Quests\Store\ContributionStore;
use Tilttek\Quests\Store\DuelStore;
use Tilttek\Quests\Store\EnrolmentStore;

/**
 * What the projection, `Quests` and `Duels` share: the catalog, the four stores, and how they are read — whose
 * progress a member's activity advances, when a chained quest opens for a subject, whether a member's activity counts,
 * and a subject's progress over a window.
 */
final readonly class Evaluator
{
    public function __construct(
        public Catalog $catalog,
        public ContributionStore $contributions,
        public EnrolmentStore $enrolments,
        public CompletionStore $completions,
        public DuelStore $duels,
    ) {
    }

    /**
     * The subject a member's activity advances: the member for a personal quest, the community for a challenge of
     * the community, the member's team for a team quest — null until they join one —, and null for a duel, whose
     * sides each advance their own.
     */
    public function subjectOf(Quest $quest, ResourceReference $member): ?ResourceReference
    {
        $enrolment = QuestKind::Team === $quest->kind ? $this->enrolments->find($quest->code, $member) : null;

        return match ($quest->kind) {
            QuestKind::Personal => $member,
            QuestKind::Community => Completion::community($quest->code),
            QuestKind::Team => $enrolment instanceof Enrolment ? $enrolment->team : null,
            QuestKind::Duel => null,
        };
    }

    /**
     * The time a chained quest is open for a subject: from its latest completion of the quest it follows; always, for
     * a quest that follows none; null while the subject has not accomplished it.
     */
    public function chain(Quest $quest, ResourceReference $subject): ?Window
    {
        if (null === $quest->after) {
            return new Window();
        }

        $latest = $this->completions->latest($quest->after, $subject);

        return $latest instanceof Completion ? new Window($latest->completedAt) : null;
    }

    /**
     * Whether a member's activity at a moment counts for the quest: always for a quest followed automatically — the
     * member's abandonment is read with the progress —, within the member's enrolment for a quest accepted, and within
     * one of the member's accepted duels for a duel. Only the activity that counts is kept as contributions.
     */
    public function counts(Quest $quest, ResourceReference $member, DateTimeImmutable $at): bool
    {
        if (QuestKind::Duel === $quest->kind) {
            return array_any($this->duels->accepted($quest->code, $member), static fn (Duel $duel): bool => $duel->covers($at));
        }

        if (!$quest->needsAcceptance()) {
            return true;
        }

        $enrolment = $this->enrolments->find($quest->code, $member);

        return $enrolment instanceof Enrolment && $enrolment->window($quest)->contains($at);
    }

    /**
     * A subject's progress over a window: the contributions of the member, of the team's members or of the community
     * that fall in it, each member's within their own enrolment.
     */
    public function progress(Quest $quest, ResourceReference $subject, Window $window): QuestProgress
    {
        if (QuestKind::Personal === $quest->kind) {
            $enrolment = $this->enrolments->find($quest->code, $subject);

            return ProgressCalculator::calculate($quest, array_filter(
                $this->contributions->of($quest->code, [$subject], $window->from, $window->until),
                static fn (Contribution $contribution): bool => self::within($quest, $enrolment, $contribution->occurredAt),
            ));
        }

        $enrolments = [];

        foreach ($this->enrolmentsOf($quest, $subject) as $enrolment) {
            $enrolments[$enrolment->member->toString()] = $enrolment;
        }

        return ProgressCalculator::calculate($quest, array_filter(
            $this->contributions->of($quest->code, null, $window->from, $window->until),
            static fn (Contribution $contribution): bool => self::within($quest, $enrolments[$contribution->member->toString()] ?? null, $contribution->occurredAt),
        ));
    }

    /**
     * A side's progress in a duel, over the time the duel lasts.
     */
    public function duelProgress(Quest $quest, Duel $duel, ResourceReference $member): QuestProgress
    {
        $window = $duel->window();

        if (!$window instanceof Window) {
            return QuestProgress::none($quest);
        }

        return ProgressCalculator::calculate($quest, $this->contributions->of($quest->code, [$member], $window->from, $window->until));
    }

    /**
     * The members an accomplished quest's rewards go to: the member, the members of the team who still follow it, or
     * every member whose activity counted towards a challenge of the community.
     *
     * @return list<ResourceReference>
     */
    public function beneficiaries(Quest $quest, ResourceReference $subject, QuestProgress $progress): array
    {
        return match ($quest->kind) {
            QuestKind::Community => array_map(ResourceReference::fromString(...), $progress->members),
            QuestKind::Team => array_values(array_map(
                static fn (Enrolment $enrolment): ResourceReference => $enrolment->member,
                array_filter($this->enrolmentsOf($quest, $subject), static fn (Enrolment $enrolment): bool => !$enrolment->isAbandoned()),
            )),
            default => [$subject],
        };
    }

    /**
     * The enrolments that decide whose activity counts for a team or the community: those of the team's members, or
     * all of the quest's.
     *
     * @return array<int, Enrolment>
     */
    private function enrolmentsOf(Quest $quest, ResourceReference $subject): array
    {
        $enrolments = $this->enrolments->ofQuest($quest->code);

        if (QuestKind::Team !== $quest->kind) {
            return $enrolments;
        }

        return array_filter($enrolments, static fn (Enrolment $enrolment): bool => $enrolment->isUnder($subject));
    }

    /**
     * Whether a contribution falls in its member's enrolment: always, for a member of a quest followed automatically
     * who never decided anything about it.
     */
    private static function within(Quest $quest, ?Enrolment $enrolment, DateTimeImmutable $at): bool
    {
        if (!$enrolment instanceof Enrolment) {
            return !$quest->needsAcceptance();
        }

        return $enrolment->window($quest)->contains($at);
    }
}
