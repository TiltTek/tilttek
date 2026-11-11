<?php

declare(strict_types=1);

namespace Tilttek\Progression;

use Tilttek\Activity\Activity;
use Tilttek\Progression\Exception\InvalidProgression;
use Tilttek\Progression\Experience\ExperienceRule;
use Tilttek\Progression\Season\SeasonSchedule;

/**
 * A line of progression: the rules that give its experience, the curve its levels follow, whom the experience goes to
 * — the member, or the gesture's object —, the seasons it starts again at, and the milestones worth a reward. A site
 * may keep several — player, game master, author.
 */
final readonly class Track
{
    /**
     * @var array<string, ExperienceRule> by name, sorted by name
     */
    public array $rules;

    /**
     * @var array<string, Milestone> by code, by threshold
     */
    public array $milestones;

    /**
     * @param list<ExperienceRule> $rules
     * @param list<Milestone>      $milestones
     */
    public function __construct(
        public string $name,
        public Curve $curve,
        array $rules,
        public Subject $subject = Subject::Member,
        public ?SeasonSchedule $seasons = null,
        array $milestones = [],
    ) {
        Names::check($name, 'track');
        $byName = [];

        foreach ($rules as $rule) {
            if (isset($byName[$rule->name])) {
                throw InvalidProgression::because(\sprintf('The track "%s" has two rules named "%s".', $name, $rule->name));
            }

            $byName[$rule->name] = $rule;
        }

        ksort($byName, \SORT_STRING);
        $this->rules = $byName;
        $byCode = [];

        foreach ($milestones as $milestone) {
            if (isset($byCode[$milestone->code])) {
                throw InvalidProgression::because(\sprintf('The track "%s" has two milestones coded "%s".', $name, $milestone->code));
            }

            $milestone->threshold($curve);
            $byCode[$milestone->code] = $milestone;
        }

        uasort($byCode, static fn (Milestone $a, Milestone $b): int => $a->threshold($curve) <=> $b->threshold($curve));
        $this->milestones = $byCode;
    }

    /**
     * The rules that reward the activity, sorted by name.
     *
     * @return list<ExperienceRule>
     */
    public function matching(Activity $activity): array
    {
        return array_values(array_filter($this->rules, static fn (ExperienceRule $rule): bool => $rule->matches($activity)));
    }

    /**
     * The milestones reached by going from one amount of experience to a larger one: those whose threshold is above
     * the first and at most the second.
     *
     * @return list<Milestone>
     */
    public function milestonesBetween(int $before, int $after): array
    {
        return array_values(array_filter($this->milestones, fn (Milestone $milestone): bool => $milestone->threshold($this->curve) > $before && $milestone->threshold($this->curve) <= $after));
    }
}
