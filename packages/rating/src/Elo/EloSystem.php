<?php

declare(strict_types=1);

namespace Tilttek\Rating\Elo;

use Override;
use Tilttek\Rating\Contract\Rating;
use Tilttek\Rating\Contract\RatingSystem;
use Tilttek\Rating\Exception\InvalidRating;
use Tilttek\Rating\GameResult;
use Tilttek\Rating\MemberRating;

/**
 * The Elo rating, for duels, games of several players and team games.
 *
 * A side's rating is the mean of its members'. Each side meets every other side: it scores 1 against a side it ranked
 * above, ½ against a side of the same rank and 0 against a side above it, and expects `1 / (1 + 10^((Rb − Ra) / s))`,
 * where `s` is the scale, 400 by default. Each member moves by `K × Σ(score − expected) / (sides − 1)`, with the K of
 * that member's games and rating, rounded once to the hundredth: the ratings are whole hundredths, so a replay gives
 * the same values.
 */
final readonly class EloSystem implements RatingSystem
{
    public const string ID = 'elo';

    /**
     * @param int  $initial the rating of a new member, 0 to 10 000 points
     * @param int  $scale   the difference of rating at which the stronger side is expected to score ten times as
     *                      much as the weaker, 1 to 10 000 points
     * @param ?int $floor   the lowest rating, 0 to the initial rating, or none
     *
     * @throws InvalidRating when a setting is out of its range
     */
    public function __construct(
        private KFactor $k = new KFactor(),
        private int $initial = 1500,
        private int $scale = 400,
        private ?int $floor = null,
    ) {
        if ($initial < 0 || $initial > 10_000) {
            throw InvalidRating::because('The initial Elo rating is 0 to 10000.');
        }

        if ($scale < 1 || $scale > 10_000) {
            throw InvalidRating::because('The scale of the Elo rating is 1 to 10000.');
        }

        if (null !== $floor && ($floor < 0 || $floor > $initial)) {
            throw InvalidRating::because('The floor of the Elo rating is 0 to the initial rating.');
        }
    }

    #[Override]
    public function id(): string
    {
        return self::ID;
    }

    #[Override]
    public function initial(): Rating
    {
        return new EloRating($this->initial * 100);
    }

    #[Override]
    public function rate(GameResult $result, array $current): array
    {
        $means = [];

        foreach ($result->sides as $side => $members) {
            $means[$side] = array_sum(array_map(fn (string $member): int => $this->hundredths($current, $member), $members)) / \count($members) / 100;
        }

        $opponents = \count($result->sides) - 1;
        $rated = [];

        foreach ($result->sides as $side => $members) {
            $surplus = 0.0;

            foreach ($means as $other => $mean) {
                if ($other !== $side) {
                    $surplus += $this->outcome($result->ranks[$side], $result->ranks[$other]) - 1 / (1 + 10 ** (($mean - $means[$side]) / $this->scale));
                }
            }

            foreach ($members as $member) {
                $before = $this->hundredths($current, $member);
                $games = $current[$member]->games ?? 0;
                $k = $this->k->for($games, $before / 100);
                $after = $before + (int) round($k * 100 * $surplus / $opponents);
                $rated[$member] = new EloRating(null === $this->floor ? $after : max($this->floor * 100, $after));
            }
        }

        return $rated;
    }

    #[Override]
    public function score(Rating $rating): int
    {
        return $this->elo($rating)->hundredths;
    }

    #[Override]
    public function isProvisional(Rating $rating): bool
    {
        $this->elo($rating);

        return false;
    }

    #[Override]
    public function restore(array $values): Rating
    {
        $hundredths = $values['hundredths'] ?? null;

        if (!\is_int($hundredths) || [] !== array_diff_key($values, ['hundredths' => true])) {
            throw InvalidRating::because('An Elo rating is its hundredths, a whole number.');
        }

        return new EloRating($hundredths);
    }

    /**
     * 1 for the side ranked above, ½ for the same rank, 0 for the side ranked below.
     */
    private function outcome(int $rank, int $other): float
    {
        return $rank < $other ? 1.0 : ($rank === $other ? 0.5 : 0.0);
    }

    /**
     * @param array<string, MemberRating> $current
     */
    private function hundredths(array $current, string $member): int
    {
        return isset($current[$member]) ? $this->elo($current[$member]->rating)->hundredths : $this->initial * 100;
    }

    private function elo(Rating $rating): EloRating
    {
        return $rating instanceof EloRating ? $rating : throw InvalidRating::because('The Elo system reads Elo ratings only.');
    }
}
