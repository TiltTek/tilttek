<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

use Tilttek\Tournament\Event\TournamentFinished;
use Tilttek\Tournament\Standings\StandingChange;

/**
 * What a result entered or corrected did: its status, how the standings of its stage moved, and the events to dispatch
 * — the end of the tournament, once it has its podium.
 */
final readonly class Recording
{
    /**
     * @param list<StandingChange>     $changes
     * @param list<TournamentFinished> $events
     */
    public function __construct(
        public RecordingStatus $status,
        public array $changes = [],
        public array $events = [],
    ) {
    }
}
