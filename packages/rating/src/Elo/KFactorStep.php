<?php

declare(strict_types=1);

namespace Tilttek\Rating\Elo;

use Tilttek\Rating\Exception\InvalidRating;

/**
 * One step of a K-factor that decreases: its K, for a member with fewer rated games than `$beforeGames`, or rated below
 * `$belowRating`, or both when both are given.
 */
final readonly class KFactorStep
{
    /**
     * @throws InvalidRating when the K is not 1 to 1 000, or the step has no condition
     */
    public function __construct(
        public int $k,
        public ?int $beforeGames = null,
        public ?int $belowRating = null,
    ) {
        KFactor::k($k);

        if (null === $beforeGames && null === $belowRating) {
            throw InvalidRating::because('A step of the K-factor applies before a number of games, below a rating, or both.');
        }

        if (null !== $beforeGames && $beforeGames < 1) {
            throw InvalidRating::because('A step of the K-factor applies before 1 game or more.');
        }
    }

    /**
     * Whether the step applies to a member with these rated games and this rating, in points.
     */
    public function applies(int $games, float $rating): bool
    {
        return (null === $this->beforeGames || $games < $this->beforeGames)
            && (null === $this->belowRating || $rating < $this->belowRating);
    }
}
