<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Event;

/**
 * Dispatched once a live session played by teams is over, for each team at the first rank of its standings with a
 * score above 0 — several on a tie: the application announces the winners or plays a celebration. It carries
 * identifiers only.
 */
final readonly class TeamWon
{
    public function __construct(
        public string $quiz,
        public string $session,
        public string $team,
    ) {
    }
}
