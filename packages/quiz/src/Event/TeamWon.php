<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Event;

/**
 * Dispatched once a live session played by teams is over, for each team at the first rank of its standings with a
 * score above 0 — several on a tie: the application announces the winners or plays a celebration. It carries
 * identifiers only.
 *
 * It is dispatched at least once: a listener is idempotent, keyed by `key()`.
 */
final readonly class TeamWon
{
    public function __construct(
        public string $quiz,
        public string $session,
        public string $team,
    ) {
    }

    /** The same each time this team's win is dispatched again. */
    public function key(): string
    {
        return \sprintf('%s/team/%s/won', $this->session, $this->team);
    }
}
