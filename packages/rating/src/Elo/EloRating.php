<?php

declare(strict_types=1);

namespace Tilttek\Rating\Elo;

use Override;
use Tilttek\Rating\Contract\Rating;

/**
 * An Elo rating, in hundredths of a point: 150 000 is a rating of 1 500.
 */
final readonly class EloRating implements Rating
{
    public function __construct(
        public int $hundredths,
    ) {
    }

    /**
     * The rating in points, to two decimals.
     */
    public function points(): float
    {
        return $this->hundredths / 100;
    }

    #[Override]
    public function toArray(): array
    {
        return ['hundredths' => $this->hundredths];
    }
}
