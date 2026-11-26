<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Event;

/**
 * The tournament has its winner and its podium: the moment for a celebration. It carries identifiers only, and comes
 * again after a correction that changes the podium.
 *
 * The library returns it to the caller, who dispatches it: a listener is idempotent, keyed by `key()`.
 */
final readonly class TournamentFinished
{
    /**
     * @param list<string> $podium the winner first, then the next places
     */
    public function __construct(
        public string $tournament,
        public string $winner,
        public array $podium,
    ) {
    }

    /** The same each time this podium is told again. */
    public function key(): string
    {
        return \sprintf('%s/finished/%s', $this->tournament, implode(',', $this->podium));
    }
}
