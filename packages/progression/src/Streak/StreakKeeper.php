<?php

declare(strict_types=1);

namespace Tilttek\Progression\Streak;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use Tilttek\Progression\Exception\UnknownStreak;
use Tilttek\Progression\ProgressionObserver;
use Tilttek\Progression\Store\StreakStore;

/**
 * Keeps the members' streaks: the gestures that make a unit active, the state that tells when a streak breaks, the
 * frozen units, and the sweep that finds the streaks broken since the members' last gestures.
 *
 * A streak breaks once: the member is told when a gesture or the sweep finds the run they were on over.
 */
final readonly class StreakKeeper
{
    public function __construct(
        private StreakRules $rules,
        private StreakStore $store,
        private ?ProgressionObserver $observer = null,
    ) {
    }

    public function rules(): StreakRules
    {
        return $this->rules;
    }

    /**
     * Makes the unit of the gesture active, then brings the member's state up to `now`.
     *
     * @param bool $announce whether a run found broken is told to the observer
     */
    public function record(StreakRule $rule, ResourceReference $member, string $activity, DateTimeImmutable $occurredAt, DateTimeZone $timezone, DateTimeImmutable $now, bool $announce): void
    {
        $this->store->add(new StreakGesture($rule->name, $member, $activity, $rule->unit->index($occurredAt, $timezone), $occurredAt));
        $this->refresh($rule, $member, $now, $timezone, $announce);
    }

    /**
     * Removes the gestures of a cancelled activity and brings the states they touched up to `now`, silently.
     */
    public function cancel(string $activity, DateTimeImmutable $now): void
    {
        foreach ($this->store->remove($activity) as $gesture) {
            try {
                $this->refresh($this->rules->get($gesture->streak), $gesture->member, $now, null, false);
            } catch (UnknownStreak) {
                // A streak the configuration no longer has: its gesture is gone, nothing is left to count.
            }
        }
    }

    /**
     * Where the member stands with the streak at `now`, in the timezone of their last gesture.
     */
    public function status(StreakRule $rule, ResourceReference $member, DateTimeImmutable $now): StreakStatus
    {
        return StreakCalculator::status(
            $rule->name,
            $this->store->units($rule->name, $member),
            $this->store->frozen($rule->name, $member),
            $rule->grace,
            $rule->unit->index($now, $this->timezone($rule, $member)),
        );
    }

    /**
     * Freezes the member's day — or week — holding the moment, read in the timezone of their last gesture; false
     * when it was frozen already.
     */
    public function freeze(StreakRule $rule, ResourceReference $member, DateTimeImmutable $moment): bool
    {
        return $this->store->freeze($rule->name, $member, $rule->unit->index($moment, $this->timezone($rule, $member)));
    }

    public function thaw(StreakRule $rule, ResourceReference $member, DateTimeImmutable $moment): bool
    {
        return $this->store->thaw($rule->name, $member, $rule->unit->index($moment, $this->timezone($rule, $member)));
    }

    /**
     * Forgets the gestures from `since` on — all of them when `since` is null —, before a rebuild replays them; the
     * states stay, brought up to date by the replay and the sweep.
     */
    public function forgetSince(?DateTimeImmutable $since): void
    {
        $this->store->forget($since);
    }

    /**
     * Finds the streaks broken since the members' last gestures and tells the observer of each.
     *
     * @return int how many broke
     */
    public function sweep(DateTimeImmutable $now): int
    {
        $broken = 0;

        foreach ($this->rules->all() as $rule) {
            // A timezone moves the current unit by one at most: the states active before it may have broken.
            foreach ($this->store->alive($rule->name, $rule->unit->index($now, new DateTimeZone('UTC'))) as $state) {
                if ($this->refresh($rule, $state->member, $now, null, true)) {
                    ++$broken;
                }
            }
        }

        return $broken;
    }

    /**
     * Brings the member's state up to `now`: a run that was alive and is over is told to the observer when
     * `announce` says so.
     *
     * @return bool whether a run broke
     */
    private function refresh(StreakRule $rule, ResourceReference $member, DateTimeImmutable $now, ?DateTimeZone $timezone, bool $announce): bool
    {
        $state = $this->store->state($rule->name, $member);
        $timezone ??= $state instanceof StreakState ? new DateTimeZone($state->timezone) : $this->rules->timezone;
        $units = $this->store->units($rule->name, $member);

        if ([] === $units) {
            $this->store->discard($rule->name, $member);

            return false;
        }

        $status = StreakCalculator::status($rule->name, $units, $this->store->frozen($rule->name, $member), $rule->grace, $rule->unit->index($now, $timezone));
        $this->store->save(new StreakState($rule->name, $member, $status->current, $status->best, $status->start, $units[\count($units) - 1], $timezone->getName()));
        $broke = $state instanceof StreakState && $state->isAlive() && (null === $status->start || $status->start > $state->lastActive);

        if ($broke && $announce) {
            $this->observer?->streakBroken(new StreakBroken($rule->name, $member, $state->current, $state->lastActive, $now));
        }

        return $broke;
    }

    private function timezone(StreakRule $rule, ResourceReference $member): DateTimeZone
    {
        $state = $this->store->state($rule->name, $member);

        return $state instanceof StreakState ? new DateTimeZone($state->timezone) : $this->rules->timezone;
    }
}
