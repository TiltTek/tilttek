<?php

declare(strict_types=1);

namespace Tilttek\Rating\Event;

/**
 * Dispatched for each member of a rated result once the new ratings are kept. It carries identifiers only: a listener
 * that shows the change, awards points or sends a message reads the rating from the store, and shows it to the member
 * alone unless the member made it public.
 */
final readonly class RatingChanged
{
    public function __construct(
        public string $game,
        public string $member,
        public string $result,
    ) {
    }
}
