<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Entry;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Projection\Projection;
use Tilttek\Quests\Duel\Duel;
use Tilttek\Quests\Duel\DuelStatus;
use Tilttek\Quests\Progress\Finish;
use Tilttek\Quests\Schedule\Occurrence;

/**
 * The projection that advances the quests: each recorded activity brings a contribution to every step that accepts
 * it, once per quest, step and activity, while the quest is open and the member's activity counts for it; then the
 * quest is evaluated for its subject — the member, their team, the community, or each duel the member is a side of —
 * and accomplished when its last step is done, which may open the quests that follow it.
 *
 * A cancellation removes the activity's contributions; a quest already accomplished stays so, its rewards given.
 * When a rule changes, `Projector::rebuild()` forgets the contributions and completions of the period, accepts again
 * the duels won in it, and replays its activity: a quest accomplished again during the rebuild is not announced a
 * second time, and one that is not is withdrawn silently.
 */
final class QuestProjection implements Projection
{
    public const string NAME = 'quests';

    /**
     * The completions the last reset forgot, by quest, occurrence and subject: accomplished again, they are not
     * announced again.
     *
     * @var array<string, string>
     */
    private array $forgotten = [];

    public function __construct(
        private readonly Evaluator $evaluator,
        private readonly ?QuestObserver $observer = null,
        private readonly string $name = self::NAME,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function filter(): Filter
    {
        $patterns = array_merge(...array_map(static fn (Quest $quest): array => $quest->patterns(), $this->evaluator->catalog->all()));

        return Filter::all()->verbs(...array_unique($patterns));
    }

    #[Override]
    public function apply(Entry $entry): void
    {
        $activity = $entry->activity;

        if ($entry->isCancellation()) {
            $this->evaluator->contributions->remove($activity->key->value);

            return;
        }

        foreach ($this->evaluator->catalog->all() as $quest) {
            $steps = $quest->stepsAccepting($activity);
            $occurrence = $quest->schedule->occurrenceAt($activity->occurredAt);

            if ([] === $steps || !$occurrence instanceof Occurrence || !$this->evaluator->counts($quest, $activity->member, $activity->occurredAt)) {
                continue;
            }

            foreach ($steps as $step) {
                $this->evaluator->contributions->add(new Contribution($quest->code, $step->code, $activity->member, $activity->key->value, $step->valueOf($activity), $activity->occurredAt));
            }

            $this->advance($quest, $activity, $occurrence);
        }
    }

    #[Override]
    public function reset(?DateTimeImmutable $since): void
    {
        $this->evaluator->contributions->forget($since);

        foreach ($this->evaluator->completions->forget($since) as $completion) {
            $key = $this->key($completion->quest, $completion->occurrence, $completion->subject);
            $this->forgotten[$key] = $key;
        }

        $this->evaluator->duels->reopen($since);
    }

    /**
     * Forgets what the last reset kept for its replay, the completions it forgot. A rebuild resets and replays in one call; a
     * long-running process, such as a worker that serves one request after another, calls this between two of them,
     * so that a quest accomplished in the next is announced.
     */
    public function clearReplay(): void
    {
        $this->forgotten = [];
    }

    private function advance(Quest $quest, Activity $activity, Occurrence $occurrence): void
    {
        $subject = $this->evaluator->subjectOf($quest, $activity->member);

        if ($subject instanceof ResourceReference) {
            $this->complete($quest, $subject, $occurrence);
        } else {
            foreach ($this->evaluator->duels->accepted($quest->code, $activity->member) as $duel) {
                $this->decide($quest, $duel, $activity);
            }
        }
    }

    private function complete(Quest $quest, ResourceReference $subject, Occurrence $occurrence): void
    {
        if ($this->evaluator->completions->find($quest->code, $occurrence->code, $subject) instanceof Completion) {
            return;
        }

        $chain = $this->evaluator->chain($quest, $subject);

        if (!$chain instanceof Window) {
            return;
        }

        $progress = $this->evaluator->progress($quest, $subject, $occurrence->window()->intersect($chain));

        if (!$progress->finish instanceof Finish) {
            return;
        }

        $completion = new Completion($quest->code, $occurrence->code, $subject, $progress->finish->at, $progress->finish->activity);
        $this->evaluator->completions->add($completion);
        $this->announce($completion, new QuestCompleted($quest, $completion, $this->evaluator->beneficiaries($quest, $subject, $progress)));

        foreach ($this->evaluator->catalog->all() as $next) {
            $opened = $next->schedule->occurrenceAt($completion->completedAt);

            if ($next->after === $quest->code && $opened instanceof Occurrence) {
                $this->complete($next, $subject, $opened);
            }
        }
    }

    private function decide(Quest $quest, Duel $duel, Activity $activity): void
    {
        // A duel the activity does not fall in reads none of it: it cannot be decided by it.
        $progress = $this->evaluator->duelProgress($quest, $duel, $activity->member);

        if (!$progress->finish instanceof Finish) {
            return;
        }

        $won = $duel->with(DuelStatus::Won, $progress->finish->at, $activity->member);
        $this->evaluator->duels->save($won);
        $completion = new Completion($quest->code, $duel->key, $activity->member, $progress->finish->at, $progress->finish->activity);
        $this->evaluator->completions->add($completion);

        if ($this->announce($completion, new QuestCompleted($quest, $completion, [$activity->member]))) {
            $this->observer?->duelWon($won);
        }
    }

    /**
     * Tells the observer of a quest accomplished, unless a rebuild accomplished it again; returns whether it told.
     */
    private function announce(Completion $completion, QuestCompleted $completed): bool
    {
        $key = $this->key($completion->quest, $completion->occurrence, $completion->subject);

        if (isset($this->forgotten[$key])) {
            unset($this->forgotten[$key]);

            return false;
        }

        $this->observer?->completed($completed);

        return true;
    }

    private function key(string $quest, string $occurrence, ResourceReference $subject): string
    {
        return json_encode([$quest, $occurrence, $subject->toString()], \JSON_THROW_ON_ERROR);
    }
}
