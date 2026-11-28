<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use Tilttek\GameCatalog\Internal\Average;

/**
 * The votes of the members on an entry, counted: by number of players, the average rating and the three choices; the
 * average weight; the median minimum age; the dependence on language most voted. They are shown beside the values of
 * the publisher, never in their place.
 */
final readonly class PollResults
{
    /** An average rating from which a number of players is among the best: 4.00. */
    public const int TOP_RATING = 400;

    /** An average rating from which a number of players is recommended: 3.00. */
    public const int RECOMMENDED_RATING = 300;

    /**
     * @param int                           $voters             the members who voted
     * @param array<int, PlayerCountResult> $playerCounts       by number of players, in increasing order
     * @param list<int>                     $ages               the minimum ages voted, in increasing order
     * @param array<string, int>            $languageDependence the votes by level of `LanguageDependence`
     */
    private function __construct(
        public int $voters,
        public array $playerCounts,
        public int $weightVotes,
        public int $weightSum,
        public array $ages,
        public array $languageDependence,
    ) {
    }

    /**
     * @param iterable<Ballot> $ballots one per member
     */
    public static function count(iterable $ballots): self
    {
        $voters = 0;
        $counts = [];
        $weightVotes = 0;
        $weightSum = 0;
        $ages = [];
        $dependence = [];

        foreach ($ballots as $ballot) {
            ++$voters;

            foreach ($ballot->ratings as $count => $rating) {
                $counts[$count] = ($counts[$count] ?? new PlayerCountResult($count))->withRating($rating);
            }

            foreach ($ballot->choices as $count => $choice) {
                $counts[$count] = ($counts[$count] ?? new PlayerCountResult($count))->withChoice($choice);
            }

            if (null !== $ballot->weight) {
                ++$weightVotes;
                $weightSum += $ballot->weight;
            }

            if (null !== $ballot->minimumAge) {
                $ages[] = $ballot->minimumAge;
            }

            if (null !== $ballot->languageDependence) {
                $level = $ballot->languageDependence->value;
                $dependence[$level] = ($dependence[$level] ?? 0) + 1;
            }
        }

        ksort($counts);
        sort($ages);

        return new self($voters, $counts, $weightVotes, $weightSum, $ages, $dependence);
    }

    public function playerCount(int $count): ?PlayerCountResult
    {
        return $this->playerCounts[$count] ?? null;
    }

    /**
     * The numbers of players whose average rating is 4.00 or more, in increasing order.
     *
     * @return list<int>
     */
    public function topPlayerCounts(): array
    {
        return $this->ratedFrom(self::TOP_RATING);
    }

    /**
     * The numbers of players whose average rating is 3.00 or more, in increasing order.
     *
     * @return list<int>
     */
    public function recommendedPlayerCounts(): array
    {
        return $this->ratedFrom(self::RECOMMENDED_RATING);
    }

    /**
     * The numbers of players most members chose as the best, in increasing order.
     *
     * @return list<int>
     */
    public function bestByChoice(): array
    {
        return $this->chosen([PlayerCountChoice::Best]);
    }

    /**
     * The numbers of players most members chose as the best or as recommended, in increasing order.
     *
     * @return list<int>
     */
    public function recommendedByChoice(): array
    {
        return $this->chosen([PlayerCountChoice::Best, PlayerCountChoice::Recommended]);
    }

    /**
     * The average weight in hundredths, 100 to 500, or `null` without a vote.
     */
    public function weight(): ?int
    {
        return Average::hundredths($this->weightSum, $this->weightVotes);
    }

    /**
     * The median of the minimum ages voted — the lower one of the two middle votes —, or `null` without a vote.
     */
    public function minimumAge(): ?int
    {
        return [] === $this->ages ? null : $this->ages[intdiv(\count($this->ages) - 1, 2)];
    }

    /**
     * The dependence on language most voted, or `null` without a vote; a tie goes to the higher level, which warns.
     */
    public function languageDependence(): ?LanguageDependence
    {
        $most = null;
        $mostVotes = 1;

        foreach (LanguageDependence::cases() as $level) {
            $votes = $this->languageDependence[$level->value] ?? null;

            if (null !== $votes && $votes >= $mostVotes) {
                $most = $level;
                $mostVotes = $votes;
            }
        }

        return $most;
    }

    /**
     * @return list<int>
     */
    private function ratedFrom(int $threshold): array
    {
        $counts = [];

        foreach ($this->playerCounts as $count => $result) {
            if ($result->averageRating() >= $threshold) {
                $counts[] = $count;
            }
        }

        return $counts;
    }

    /**
     * @param list<PlayerCountChoice> $choices
     *
     * @return list<int>
     */
    private function chosen(array $choices): array
    {
        $counts = [];

        foreach ($this->playerCounts as $count => $result) {
            if (\in_array($result->choice(), $choices, true)) {
                $counts[] = $count;
            }
        }

        return $counts;
    }
}
