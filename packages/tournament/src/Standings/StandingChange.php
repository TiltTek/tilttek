<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Standings;

/**
 * How a participant's place moved between two standings: the rank and the points before and after.
 */
final readonly class StandingChange
{
    public function __construct(
        public string $participant,
        public ?int $rankBefore,
        public ?int $rankAfter,
        public ?int $pointsBefore,
        public ?int $pointsAfter,
    ) {
    }
}
