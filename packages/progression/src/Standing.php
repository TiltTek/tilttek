<?php

declare(strict_types=1);

namespace Tilttek\Progression;

use Tilttek\Progression\Season\Season;

/**
 * Where a subject stands on a track now: the season under way — none on a track without seasons, or between two
 * seasons —, the level of the experience that counts — the season's, or all of it on a track without seasons —, and
 * the experience of every season together.
 */
final readonly class Standing
{
    public function __construct(
        public Track $track,
        public ?Season $season,
        public Level $level,
        public int $lifetime,
    ) {
    }
}
