<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Event;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A puzzle that opens: by the puzzles solved before it, or by the host. Dispatched once the move is made; it names identifiers only.
 */
final readonly class PuzzleOpened
{
    /**
     * @param bool $byHost whether the host opened it
     */
    public function __construct(
        public string $scenario,
        public ResourceReference $team,
        public string $puzzle,
        public DateTimeImmutable $at,
        public bool $byHost,
    ) {
    }
}
