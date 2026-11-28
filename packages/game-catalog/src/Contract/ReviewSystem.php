<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Contract;

use Tilttek\GameCatalog\Experience;

/**
 * A port to the reviews the application keeps: the catalogue gives it the experience of the author, frozen when the
 * review was written, to show beside the review.
 */
interface ReviewSystem
{
    /**
     * The experience kept for the review, if one is.
     */
    public function experience(string $review): ?Experience;

    public function keepExperience(string $review, string $entry, string $member, Experience $experience): void;
}
