<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Event;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A hint asked by the players, shown on the hint screen. The text is read from the scenario by its puzzle and
 * tier. It names identifiers only.
 */
final readonly class HintRequested
{
    /**
     * @param int $tier    from 1, the first hint of the puzzle
     * @param int $penalty the seconds it adds to the final time
     */
    public function __construct(
        public string $room,
        public ResourceReference $team,
        public ?string $session,
        public string $puzzle,
        public int $tier,
        public int $penalty,
        public DateTimeImmutable $at,
    ) {
    }
}
