<?php

declare(strict_types=1);

namespace Tilttek\Rating\Event;

/**
 * Dispatched after `RatingChanged` when a member's rating of a game goes above the highest it ever was, once the member
 * has played the rated games `Ratings` asks for before celebrating: the moment for a celebration. It carries
 * identifiers only.
 *
 * It is dispatched at least once: a listener is idempotent, keyed by `key()`.
 */
final readonly class PeakReached
{
    public function __construct(
        public string $game,
        public string $member,
        public string $result,
    ) {
    }

    /** The same each time this peak is dispatched again. */
    public function key(): string
    {
        return \sprintf('%s/%s/%s/peak', $this->game, $this->result, $this->member);
    }
}
