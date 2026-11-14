<?php

declare(strict_types=1);

namespace Tilttek\Rating\OpenSkill;

use Override;
use Tilttek\Rating\Contract\Rating;
use Tilttek\Rating\Contract\RatingSystem;
use Tilttek\Rating\Exception\InvalidRating;
use Tilttek\Rating\GameResult;
use Tilttek\Rating\MemberRating;

/**
 * OpenSkill: the Bayesian approximation of online ranking of Weng and Lin (2011), for duels, games of several players
 * and team games, with draws.
 *
 * A skill is a normal distribution, its mean `mu` and its deviation `sigma`. Before a game, each sigma grows by `tau`,
 * so that a skill can still change; a side's mean is the sum of its members' means, and its variance the sum of their
 * variances. The model gives each side a correction of its mean, Ω, and of its variance, Δ; each member takes the share
 * of its own variance in the side's: `mu += σ²/σ²side × Ω` and `σ² ×= max(1 − σ²/σ²side × Δ, κ)`. The standings order
 * the members by the conservative estimate `mu − z × sigma`, so that a new member climbs as the uncertainty shrinks.
 *
 * `mu` and `sigma` are kept in millionths, rounded once per game, so a replay gives the same values.
 */
final readonly class OpenSkillSystem implements RatingSystem
{
    private float $sigma;

    private float $beta;

    private float $tau;

    /**
     * @param float  $mu               the mean skill of a new member, 1 to 1 000
     * @param ?float $sigma            its deviation, above 0 and at most the mean; `mu / 3` by default
     * @param ?float $beta             the deviation of a performance around the skill, above 0; `sigma / 2` by default
     * @param ?float $tau              how much the deviation grows before each game, 0 or more; `mu / 300` by default
     * @param float  $kappa            the smallest share of a variance a game keeps, above 0 and below 1
     * @param float  $drawMargin       the margin of a draw in the Thurstone-Mosteller model, 0 or more
     * @param float  $z                the deviations taken off the mean for the conservative estimate, 0 to 10
     * @param ?float $provisionalSigma the deviation above which a skill is provisional, above 0, or none
     *
     * @throws InvalidRating when a setting is out of its range
     */
    public function __construct(
        private OpenSkillModel $model = OpenSkillModel::PlackettLuce,
        private float $mu = 25.0,
        ?float $sigma = null,
        ?float $beta = null,
        ?float $tau = null,
        private float $kappa = 0.0001,
        private float $drawMargin = 0.1,
        private float $z = 3.0,
        private ?float $provisionalSigma = null,
    ) {
        $this->sigma = $sigma ?? $mu / 3;
        $this->beta = $beta ?? $this->sigma / 2;
        $this->tau = $tau ?? $mu / 300;

        if ($mu < 1 || $mu > 1000) {
            throw InvalidRating::because('The initial mean skill is 1 to 1000.');
        }

        if ($this->sigma <= 0 || $this->sigma > $mu) {
            throw InvalidRating::because('The initial deviation of a skill is above 0 and at most its mean.');
        }

        if ($this->beta <= 0 || $this->tau < 0 || $drawMargin < 0) {
            throw InvalidRating::because('The deviation of a performance is above 0; the growth of a deviation and the draw margin are 0 or more.');
        }

        if ($kappa <= 0 || $kappa >= 1) {
            throw InvalidRating::because('The smallest share of a variance kept is above 0 and below 1.');
        }

        if ($z < 0 || $z > 10) {
            throw InvalidRating::because('The conservative estimate takes 0 to 10 deviations off the mean.');
        }

        if (null !== $provisionalSigma && $provisionalSigma <= 0) {
            throw InvalidRating::because('The deviation above which a skill is provisional is above 0.');
        }
    }

    #[Override]
    public function id(): string
    {
        return $this->model->value;
    }

    #[Override]
    public function initial(): Rating
    {
        return SkillRating::of($this->mu, $this->sigma);
    }

    #[Override]
    public function rate(GameResult $result, array $current): array
    {
        $means = [];
        $variances = [];
        $sides = [];

        foreach ($result->sides as $side => $members) {
            $means[$side] = 0.0;
            $variances[$side] = 0.0;

            foreach ($members as $member) {
                $skill = $this->skill($current, $member);
                $variance = $skill->deviation() ** 2 + $this->tau ** 2;
                $sides[$side][$member] = [$skill->mean(), $variance];
                $means[$side] += $skill->mean();
                $variances[$side] += $variance;
            }
        }

        $corrections = OpenSkillModel::PlackettLuce === $this->model
            ? $this->plackettLuce($means, $variances, $result->ranks)
            : $this->thurstoneMosteller($means, $variances, $result->ranks);
        $rated = [];

        foreach ($sides as $side => $members) {
            [$omega, $delta] = $corrections[$side];

            foreach ($members as $member => [$mean, $variance]) {
                $share = $variance / $variances[$side];
                $rated[$member] = SkillRating::of($mean + $share * $omega, sqrt($variance * max(1 - $share * $delta, $this->kappa)));
            }
        }

        return $rated;
    }

    #[Override]
    public function score(Rating $rating): int
    {
        return (int) round($this->skillOf($rating)->conservative($this->z) * 100);
    }

    #[Override]
    public function isProvisional(Rating $rating): bool
    {
        $skill = $this->skillOf($rating);

        return null !== $this->provisionalSigma && $skill->deviation() > $this->provisionalSigma;
    }

    #[Override]
    public function restore(array $values): Rating
    {
        $mu = $values['mu'] ?? null;
        $sigma = $values['sigma'] ?? null;

        if (!\is_int($mu) || !\is_int($sigma) || [] !== array_diff_key($values, ['mu' => true, 'sigma' => true])) {
            throw InvalidRating::because('A skill is its mu and its sigma, whole numbers of millionths.');
        }

        return new SkillRating($mu, $sigma);
    }

    /**
     * The Plackett-Luce corrections: side `q` is chosen first among the sides ranked at or below it, each with a weight
     * `e^(mu/c)`; a side ranked with others shares each choice with them.
     *
     * @param array<int, float> $means
     * @param array<int, float> $variances
     * @param list<int>         $ranks
     *
     * @return array<int, array{0: float, 1: float}>
     */
    private function plackettLuce(array $means, array $variances, array $ranks): array
    {
        $c = 0.0;

        foreach ($variances as $variance) {
            $c += $variance + $this->beta ** 2;
        }

        $c = sqrt($c);
        // The weights are taken relative to the strongest side: their ratios are the same, and none overflows.
        $top = -\INF;

        foreach ($means as $mean) {
            $top = max($top, $mean);
        }

        $weights = array_map(static fn (float $mean): float => exp(($mean - $top) / $c), $means);
        $sums = [];
        $ties = [];

        foreach ($ranks as $q => $rank) {
            $sums[$q] = 0.0;
            $ties[$q] = 0;

            foreach ($ranks as $s => $other) {
                if ($other >= $rank) {
                    $sums[$q] += $weights[$s];
                }

                if ($other === $rank) {
                    ++$ties[$q];
                }
            }
        }

        $corrections = [];

        foreach ($ranks as $i => $rank) {
            $omega = 0.0;
            $delta = 0.0;

            foreach ($ranks as $q => $other) {
                if ($other <= $rank) {
                    $share = $weights[$i] / $sums[$q];
                    $delta += $share * (1 - $share) / $ties[$q];
                    $omega += ($q === $i ? 1 - $share : -$share) / $ties[$q];
                }
            }

            $gamma = sqrt($variances[$i]) / $c;
            $corrections[$i] = [$omega * $variances[$i] / $c, $delta * $gamma * $variances[$i] / $c ** 2];
        }

        return $corrections;
    }

    /**
     * The Thurstone-Mosteller corrections: each pair of sides compared as a duel, with the draw margin.
     *
     * @param array<int, float> $means
     * @param array<int, float> $variances
     * @param list<int>         $ranks
     *
     * @return array<int, array{0: float, 1: float}>
     */
    private function thurstoneMosteller(array $means, array $variances, array $ranks): array
    {
        $corrections = [];

        foreach ($ranks as $i => $rank) {
            $omega = 0.0;
            $delta = 0.0;

            foreach ($ranks as $q => $other) {
                if ($q === $i) {
                    continue;
                }

                $c = sqrt($variances[$i] + $variances[$q] + 2 * $this->beta ** 2);
                $x = ($means[$i] - $means[$q]) / $c;
                $t = $this->drawMargin / $c;
                $gamma = sqrt($variances[$i]) / $c;

                [$v, $w] = match (true) {
                    $rank < $other => Gaussian::win($x, $t),
                    $rank > $other => Gaussian::win(-$x, $t),
                    default => Gaussian::draw($x, $t),
                };
                $omega += ($rank > $other ? -$v : $v) * $variances[$i] / $c;
                $delta += $gamma * $variances[$i] / $c ** 2 * $w;
            }

            $corrections[$i] = [$omega, $delta];
        }

        return $corrections;
    }

    /**
     * @param array<string, MemberRating> $current
     */
    private function skill(array $current, string $member): SkillRating
    {
        return isset($current[$member]) ? $this->skillOf($current[$member]->rating) : SkillRating::of($this->mu, $this->sigma);
    }

    private function skillOf(Rating $rating): SkillRating
    {
        return $rating instanceof SkillRating ? $rating : throw InvalidRating::because('The OpenSkill system reads skills only.');
    }
}
