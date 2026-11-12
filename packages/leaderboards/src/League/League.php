<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\League;

use Tilttek\Leaderboards\Exception\InvalidLeaderboard;
use Tilttek\Leaderboards\Names;

/**
 * The league of a weekly board: its tiers, from the lowest to the highest, how many members a division holds, how
 * many of each division go up a tier and down a tier at the end of the week, and how its divisions are formed.
 *
 * Formed by volume, the members of a tier are split into cohorts by their score the week before — none, 1, 2 to 3, 4
 * to 7, each cohort twice the one before —, and a division holds the members of one cohort.
 *
 * *Five tiers of about thirty, the first seven up and the last five down*: `new League(['bronze', 'silver', 'gold',
 * 'sapphire', 'diamond'])`.
 */
final readonly class League
{
    public const int MAX_TIERS = 50;

    public const int MAX_DIVISION = 100;

    /**
     * The cohort of a member without a score the week before, and of every member when the divisions are formed by
     * arrival.
     */
    public const int NO_COHORT = 0;

    /**
     * @var non-empty-list<string> the tiers, from the lowest to the highest
     */
    public array $tiers;

    /**
     * @param list<string> $tiers from the lowest to the highest
     */
    public function __construct(
        array $tiers,
        public int $divisionSize = 30,
        public int $promoted = 7,
        public int $relegated = 5,
        public Formation $formedBy = Formation::Arrival,
    ) {
        foreach ($tiers as $tier) {
            Names::check($tier, 'tier');
        }

        if ([] === $tiers || \count($tiers) > self::MAX_TIERS || \count(array_unique($tiers)) !== \count($tiers)) {
            throw InvalidLeaderboard::because(\sprintf('A league has 1 to %d tiers, each named once.', self::MAX_TIERS));
        }

        if ($divisionSize < 2 || $divisionSize > self::MAX_DIVISION) {
            throw InvalidLeaderboard::because(\sprintf('A division holds 2 to %d members; %d is not a division size.', self::MAX_DIVISION, $divisionSize));
        }

        if ($promoted < 0 || $relegated < 0 || $promoted + $relegated > $divisionSize) {
            throw InvalidLeaderboard::because(\sprintf('A division of %d sends 0 or more members up and 0 or more down, %d at most in all; %d up and %d down do not fit.', $divisionSize, $divisionSize, $promoted, $relegated));
        }

        $this->tiers = $tiers;
    }

    /**
     * The cohort, when the divisions are formed by volume, of a member whose score the week before was that value: 0
     * without a score, then 1 for 1, 2 for 2 to 3, 3 for 4 to 7, one more each time the score doubles.
     */
    public function cohortOf(int $lastWeek): int
    {
        $cohort = self::NO_COHORT;

        while ($lastWeek > 0) {
            ++$cohort;
            $lastWeek >>= 1;
        }

        return $cohort;
    }

    /**
     * The tier a new member enters.
     */
    public function lowest(): string
    {
        return $this->tiers[0];
    }

    /**
     * The position of the tier, 0 for the lowest; a tier the league no longer has counts as the lowest.
     */
    public function level(string $tier): int
    {
        $level = array_search($tier, $this->tiers, true);

        return \is_int($level) ? $level : 0;
    }

    /**
     * The tier a member of the tier reaches by the movement; the highest tier sends nobody up and the lowest nobody
     * down.
     */
    public function after(string $tier, Movement $movement): string
    {
        $level = $this->level($tier) + match ($movement) {
            Movement::Promoted => 1,
            Movement::Stayed => 0,
            Movement::Relegated => -1,
        };

        return $this->tiers[max(0, min(\count($this->tiers) - 1, $level))];
    }

    /**
     * How many members of a division of that tier and size go up: none from the highest tier.
     */
    public function promotedFrom(string $tier, int $members): int
    {
        return $this->level($tier) === \count($this->tiers) - 1 ? 0 : min($this->promoted, $members);
    }

    /**
     * How many members of a division of that tier and size go down: the last ones, ranked below the full division's
     * last places that stay — so a division that did not fill sends fewer down, or none —, and none from the lowest
     * tier.
     */
    public function relegatedFrom(string $tier, int $members): int
    {
        return 0 === $this->level($tier) ? 0 : min($this->relegated, max(0, $members - $this->divisionSize + $this->relegated));
    }
}
