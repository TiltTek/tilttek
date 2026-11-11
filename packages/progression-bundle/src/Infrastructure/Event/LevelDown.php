<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Infrastructure\Event;

use Tilttek\Progression\LevelChange;

/**
 * A subject went back to a lower level of a track, when an activity was cancelled.
 */
final readonly class LevelDown
{
    public function __construct(
        public LevelChange $change,
    ) {
    }
}
