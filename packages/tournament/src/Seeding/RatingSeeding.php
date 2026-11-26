<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Seeding;

use Override;
use Tilttek\Rating\Contract\RatingStore;
use Tilttek\Tournament\Contract\SeedingSource;

/**
 * The strengths of the ratings of `tilttek/rating` for one game: the score of each participant's rating, private or
 * public, since the seeding shows an order and never the score. A participant without a rating of the game is placed
 * after the others.
 *
 * Install `tilttek/rating` to use it.
 */
final readonly class RatingSeeding implements SeedingSource
{
    public function __construct(
        private RatingStore $ratings,
        private string $game,
    ) {
    }

    #[Override]
    public function strengths(array $participants): array
    {
        $strengths = [];

        foreach ($this->ratings->find($this->game, $participants) as $rating) {
            $strengths[$rating->member] = $rating->score;
        }

        return $strengths;
    }
}
