<?php

declare(strict_types=1);

namespace Tilttek\Progression;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Progression\Exception\UnknownStreak;
use Tilttek\Progression\Exception\UnknownTrack;
use Tilttek\Progression\Season\Season;
use Tilttek\Progression\Season\SeasonSchedule;
use Tilttek\Progression\Store\ExperienceStore;
use Tilttek\Progression\Store\MilestoneStore;
use Tilttek\Progression\Store\StreakStore;
use Tilttek\Progression\Streak\StreakKeeper;
use Tilttek\Progression\Streak\StreakRule;
use Tilttek\Progression\Streak\StreakStatus;

/**
 * The members' progression: where a member — or a character — stands on each track, the seasons they finished, the
 * milestones they reached, their streaks, the days they freeze, the sweep that finds the streaks broken, and the
 * erasure of a member.
 *
 * Experience is never spent and never written by hand: it comes from the activity, through `ProgressionProjection`.
 */
final readonly class Progression
{
    public function __construct(
        private Tracks $tracks,
        private StreakKeeper $streaks,
        private ExperienceStore $experience,
        private MilestoneStore $milestones,
        private StreakStore $streakStore,
        private ClockInterface $clock,
    ) {
    }

    public function tracks(): Tracks
    {
        return $this->tracks;
    }

    /**
     * Where the subject stands on the track now.
     *
     * @throws UnknownTrack
     */
    public function standing(ResourceReference $subject, string $track): Standing
    {
        $track = $this->tracks->get($track);
        $season = $track->seasons?->at($this->clock->now());
        $lifetime = $this->experience->lifetime($track->name, $subject);
        $experience = $track->seasons instanceof SeasonSchedule ? $season instanceof Season ? $this->experience->total($track->name, $subject, $season->code) : 0 : ($lifetime);

        return new Standing($track, $season, $track->curve->level($experience), $lifetime);
    }

    /**
     * Where the subject stands on every track, by name.
     *
     * @return list<Standing>
     */
    public function standings(ResourceReference $subject): array
    {
        return array_map(fn (Track $track): Standing => $this->standing($subject, $track->name), $this->tracks->all());
    }

    /**
     * What the subject reached in each past season of the track, the oldest first; the season under way and the
     * seasons the track no longer has are left out.
     *
     * @return list<SeasonResult>
     *
     * @throws UnknownTrack
     */
    public function history(ResourceReference $subject, string $track): array
    {
        $track = $this->tracks->get($track);
        $now = $this->clock->now();
        $results = [];

        foreach ($this->experience->seasons($track->name, $subject) as $total) {
            $season = $track->seasons?->named($total->season);

            if ($season instanceof Season && $season->end <= $now) {
                $results[] = new SeasonResult($season, $track->curve->level($total->experience));
            }
        }

        usort($results, static fn (SeasonResult $a, SeasonResult $b): int => $a->season->start <=> $b->season->start);

        return $results;
    }

    /**
     * The milestones the subject reached, in the order they were reached.
     *
     * @return list<ReachedMilestone>
     */
    public function milestones(ResourceReference $subject): array
    {
        return $this->milestones->of($subject);
    }

    /**
     * Where the member stands with the streak now.
     *
     * @throws UnknownStreak
     */
    public function streak(ResourceReference $member, string $streak): StreakStatus
    {
        return $this->streaks->status($this->streaks->rules()->get($streak), $member, $this->clock->now());
    }

    /**
     * Where the member stands with every streak, by name.
     *
     * @return list<StreakStatus>
     */
    public function streaks(ResourceReference $member): array
    {
        return array_map(fn (StreakRule $rule): StreakStatus => $this->streak($member, $rule->name), $this->streaks->rules()->all());
    }

    /**
     * Freezes the member's day — or week — holding the moment: a missed unit frozen keeps the streak alive without
     * adding to it. The application decides who may freeze and what it costs — a reward bought with points, a few a
     * month —; false when the unit was frozen already.
     *
     * @throws UnknownStreak
     */
    public function freeze(ResourceReference $member, string $streak, DateTimeImmutable $moment): bool
    {
        return $this->streaks->freeze($this->streaks->rules()->get($streak), $member, $moment);
    }

    /**
     * Thaws a unit frozen by mistake or whose price was refunded; false when it was not frozen.
     *
     * @throws UnknownStreak
     */
    public function thaw(ResourceReference $member, string $streak, DateTimeImmutable $moment): bool
    {
        return $this->streaks->thaw($this->streaks->rules()->get($streak), $member, $moment);
    }

    /**
     * Finds the streaks broken since the members' last gestures — to run once a day, or once an hour for members
     * across timezones — and tells the observer of each.
     *
     * @return int how many broke
     */
    public function sweep(): int
    {
        return $this->streaks->sweep($this->clock->now());
    }

    /**
     * Erases the member: the experience they and their gestures earned, their milestones, their streaks.
     */
    public function erase(ResourceReference $member): Erasure
    {
        return new Erasure(
            $this->experience->eraseMember($member),
            $this->milestones->eraseMember($member),
            $this->streakStore->eraseMember($member),
        );
    }

    /**
     * The experience gains of the subject, for an export.
     *
     * @return list<Experience\Gain>
     */
    public function gains(ResourceReference $subject): array
    {
        return $this->experience->of($subject);
    }
}
