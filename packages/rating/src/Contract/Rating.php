<?php

declare(strict_types=1);

namespace Tilttek\Rating\Contract;

/**
 * A member's rating of a game in one rating system, kept in whole numbers of a fixed point so that replaying the same
 * results always gives the same values, with no error carried from one game to the next.
 */
interface Rating
{
    /**
     * The rating as whole numbers, by name: what a store keeps and `RatingSystem::restore()` reads back.
     *
     * @return array<string, int>
     */
    public function toArray(): array;
}
