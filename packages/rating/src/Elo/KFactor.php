<?php

declare(strict_types=1);

namespace Tilttek\Rating\Elo;

use Tilttek\Rating\Exception\InvalidRating;

/**
 * How far one game moves an Elo rating: a fixed K, or a K that decreases as a member plays and climbs. The steps are
 * read in the order given, and the first that applies gives the K; without one, the last K is used.
 */
final readonly class KFactor
{
    /** The largest K. */
    public const int MAX = 1000;

    /** @var list<KFactorStep> */
    public array $steps;

    /**
     * @throws InvalidRating when a K is not 1 to 1 000
     */
    public function __construct(
        public int $k = 32,
        KFactorStep ...$steps,
    ) {
        self::k($k);
        $this->steps = array_values($steps);
    }

    /**
     * The decreasing K of the international chess federation for adults: 40 for the first 30 games, 20 below 2 400,
     * then 10.
     */
    public static function chess(): self
    {
        return new self(10, new KFactorStep(40, beforeGames: 30), new KFactorStep(20, belowRating: 2400));
    }

    /**
     * The K of a member with these rated games and this rating, in points.
     */
    public function for(int $games, float $rating): int
    {
        foreach ($this->steps as $step) {
            if ($step->applies($games, $rating)) {
                return $step->k;
            }
        }

        return $this->k;
    }

    /**
     * @throws InvalidRating when the K is not 1 to 1 000
     *
     * @internal
     */
    public static function k(int $k): int
    {
        if ($k < 1 || $k > self::MAX) {
            throw InvalidRating::because(\sprintf('A K-factor is 1 to %d.', self::MAX));
        }

        return $k;
    }
}
