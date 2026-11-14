<?php

declare(strict_types=1);

namespace Tilttek\Rating\Event;

/**
 * Dispatched after `RatingChanged` when a member's rating of a game goes above the highest it ever was, once the member
 * has played the rated games `Ratings` asks for before celebrating: the moment for a celebration. It carries
 * identifiers only.
 */
final readonly class PeakReached
{
    public function __construct(
        public string $game,
        public string $member,
        public string $result,
    ) {
    }
}
