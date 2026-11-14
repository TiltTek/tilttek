<?php

declare(strict_types=1);

namespace Tilttek\Progression;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Activity\Entry;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Projection\Projection;
use Tilttek\Progression\Experience\Cap;
use Tilttek\Progression\Experience\ExperienceRule;
use Tilttek\Progression\Experience\Gain;
use Tilttek\Progression\Store\ExperienceStore;
use Tilttek\Progression\Store\MilestoneStore;
use Tilttek\Progression\Streak\FixedTimezone;
use Tilttek\Progression\Streak\StreakKeeper;
use Tilttek\Progression\Streak\TimezoneResolver;

/**
 * The projection that turns the members' activity into experience and streaks: each recorded activity a track's rule
 * rewards brings its experience to the member — or to the gesture's object —, in the season it fell in, up to the
 * rule's cap; a level crossed is told to the observer, and so is a milestone reached, once. Each activity a streak
 * reads makes the member's day — or week, in their timezone — active, and a run found over is told. A cancellation
 * takes back what the activity brought: a level lost is told, a milestone reached stays reached.
 *
 * When a rule changes, `Projector::rebuild()` forgets the experience and the streak days of the period and replays
 * its activity under the new rules. While it replays the entries written before the reset, only the levels above
 * those held before are told, and no broken streak is: the members were told then.
 *
 * The observer is told of a level gained and of a milestone at least once. A gain is kept with its notice due, and a
 * milestone reached likewise, both before the observer is told; each is noted delivered once the observer was told.
 * When the observer fails, the notices stay due: the entry handed again — the same request repeated, or a catch-up —,
 * the subject's next activity, or `deliver()` gives them. The levels the gains still due brought are told at once,
 * from the level before the first of them to the level after the last. The observer may thus be told twice of the
 * same change, and must be idempotent: it recognises a level by its track, subject, season and number, a milestone by
 * its track, code, subject and season. A level lost is told once, when the cancellation is applied.
 */
final class ProgressionProjection implements Projection
{
    public const string NAME = 'progression';

    /**
     * The levels held before the last reset, by track, subject and season.
     *
     * @var array<string, array<string, array<string, int>>>
     */
    private array $held = [];

    private ?DateTimeImmutable $resetAt = null;

    private readonly TimezoneResolver $timezones;

    public function __construct(
        private readonly Tracks $tracks,
        private readonly StreakKeeper $streaks,
        private readonly ExperienceStore $experience,
        private readonly MilestoneStore $milestones,
        private readonly ClockInterface $clock,
        ?TimezoneResolver $timezones = null,
        private readonly ?ProgressionObserver $observer = null,
        private readonly string $name = self::NAME,
    ) {
        $this->timezones = $timezones ?? new FixedTimezone($streaks->rules()->timezone);
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function filter(): Filter
    {
        return Filter::all()->verbs(...array_unique([...$this->tracks->patterns(), ...$this->streaks->rules()->patterns()]));
    }

    #[Override]
    public function apply(Entry $entry): void
    {
        $replaying = $this->resetAt instanceof DateTimeImmutable && $entry->at < $this->resetAt;

        if ($entry->isCancellation()) {
            $this->takeBack($entry, $replaying);
            $this->streaks->cancel($entry->activity->key->value, $entry->at);

            return;
        }

        $activity = $entry->activity;

        foreach ($this->tracks->all() as $track) {
            $subject = Subject::Member === $track->subject ? $activity->member : $activity->object;

            if (!$subject instanceof ResourceReference) {
                continue;
            }

            foreach ($track->matching($activity) as $rule) {
                $this->earn($track, $rule, $subject, $entry);
            }

            $this->tell($subject, $replaying);
        }

        foreach ($this->streaks->rules()->all() as $rule) {
            if ($rule->matches($activity)) {
                $this->streaks->record($rule, $activity->member, $activity->key->value, $activity->occurredAt, $this->timezones->timezoneFor($activity), $entry->at, !$replaying);
            }
        }
    }

    #[Override]
    public function reset(?DateTimeImmutable $since): void
    {
        $this->held = [];

        foreach ($this->experience->totalsSince($since) as $total) {
            if ($this->tracks->has($total->track)) {
                $this->held[$total->track][$total->subject->toString()][$total->season] = $this->tracks->get($total->track)->curve->level($total->experience)->number;
            }
        }

        $this->experience->forget($since);
        $this->streaks->forgetSince($since);
        $this->resetAt = $this->clock->now();
    }

    /**
     * Gives the observer the notices the subject is still owed — the levels its gains due brought, then its milestones
     * due —; returns how many gains and milestones were settled. A notice of a track the configuration no longer holds
     * is let go without the observer.
     */
    public function deliver(ResourceReference $subject): int
    {
        return $this->tell($subject, false);
    }

    /**
     * Forgets what the last reset kept for its replay, the levels held before it and its moment. A rebuild resets and replays in one call; a
     * long-running process, such as a worker that serves one request after another, calls this between two of them,
     * so that the activity of the next is told as new.
     */
    public function clearReplay(): void
    {
        $this->held = [];
        $this->resetAt = null;
    }

    /**
     * Keeps the gain the rule gives the subject, with the milestones it reaches, each with its notice due.
     */
    private function earn(Track $track, ExperienceRule $rule, ResourceReference $subject, Entry $entry): void
    {
        $activity = $entry->activity;
        $season = $track->seasons?->at($activity->occurredAt)->code ?? '';
        $amount = $rule->experienceFor($activity);

        if ($rule->cap instanceof Cap) {
            [$since, $until] = $rule->cap->period->bounds($activity->occurredAt, $this->tracks->timezone);
            $amount = min($amount, $rule->cap->amount - $this->experience->earned($track->name, $rule->name, $subject, $since, $until));
        }

        if ($amount < 1) {
            return;
        }

        $before = $this->experience->total($track->name, $subject, $season);

        if (!$this->experience->add(new Gain($track->name, $rule->name, $subject, $activity->member, $activity->key->value, $amount, $activity->occurredAt, $season))) {
            return;
        }

        foreach ($track->milestonesBetween($before, $before + $amount) as $milestone) {
            $this->milestones->reach(new ReachedMilestone($track->name, $milestone->code, $subject, $activity->member, $season, $activity->occurredAt, $activity->key->value));
        }
    }

    /**
     * Tells the observer the levels the subject's gains due brought, by track and season, then its milestones due, and
     * notes each delivered.
     */
    private function tell(ResourceReference $subject, bool $replaying): int
    {
        $due = [];

        foreach ($this->experience->undelivered($subject) as $gain) {
            $due[json_encode([$gain->track, $gain->season], \JSON_THROW_ON_ERROR)][] = $gain;
        }

        $settled = 0;

        foreach ($due as $gains) {
            if ($this->tracks->has($gains[0]->track)) {
                $this->leveled($this->tracks->get($gains[0]->track)->curve, $subject, $gains, $replaying);
            }

            foreach ($gains as $gain) {
                $this->experience->markDelivered($gain);
                ++$settled;
            }
        }

        foreach ($this->milestones->undelivered($subject) as $reached) {
            if ($this->tracks->has($reached->track)) {
                $this->observer?->milestoneReached($reached);
            }

            $this->milestones->markDelivered($reached);
            ++$settled;
        }

        return $settled;
    }

    /**
     * Tells the level the subject gained on a track and in a season by these gains, still due, in the order they
     * occurred: from the level before the first to the level now.
     *
     * @param non-empty-list<Gain> $gains
     */
    private function leveled(Curve $curve, ResourceReference $subject, array $gains, bool $replaying): void
    {
        $last = $gains[\count($gains) - 1];
        $season = $last->season;
        $after = $this->experience->total($last->track, $subject, $season);
        $from = $curve->level($after - array_sum(array_map(static fn (Gain $gain): int => $gain->amount, $gains)))->number;
        $to = $curve->level($after)->number;

        if ($to > $from && (!$replaying || $to > ($this->held[$last->track][$subject->toString()][$season] ?? $from))) {
            $this->held[$last->track][$subject->toString()][$season] = $to;
            $this->observer?->leveledUp(new LevelChange($last->track, $subject, $season, $from, $to, $last->occurredAt, $last->activity));
        }
    }

    private function takeBack(Entry $entry, bool $replaying): void
    {
        $removed = [];

        foreach ($this->experience->remove($entry->activity->key->value) as $gain) {
            $removed[$gain->track][$gain->subject->toString()][$gain->season][] = $gain;
        }

        foreach ($removed as $track => $subjects) {
            if ($replaying || !$this->tracks->has($track)) {
                continue;
            }

            foreach ($subjects as $seasons) {
                foreach ($seasons as $gains) {
                    $this->lowered($this->tracks->get($track)->curve, $gains, $entry->at);
                }
            }
        }
    }

    /**
     * Tells the level a subject lost when the gains of an activity on a track and in a season were taken back.
     *
     * @param non-empty-list<Gain> $gains
     */
    private function lowered(Curve $curve, array $gains, DateTimeImmutable $at): void
    {
        $gain = $gains[0];
        $after = $this->experience->total($gain->track, $gain->subject, $gain->season);
        $from = $curve->level($after + array_sum(array_map(static fn (Gain $gain): int => $gain->amount, $gains)))->number;
        $to = $curve->level($after)->number;

        if ($to < $from) {
            $this->observer?->leveledDown(new LevelChange($gain->track, $gain->subject, $gain->season, $from, $to, $at, $gain->activity));
        }
    }
}
