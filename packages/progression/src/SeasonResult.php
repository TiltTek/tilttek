<?php

declare(strict_types=1);

namespace Tilttek\Progression;

use Tilttek\Progression\Season\Season;

/**
 * What a subject reached on a track in a past season.
 */
final readonly class SeasonResult
{
    public function __construct(
        public Season $season,
        public Level $level,
    ) {
    }
}
