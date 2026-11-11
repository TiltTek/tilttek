<?php

declare(strict_types=1);

namespace Tilttek\Progression;

/**
 * What erasing a member removed: the experience gains, the milestones reached, and the streak gestures, frozen units
 * and states.
 */
final readonly class Erasure
{
    public function __construct(
        public int $gains = 0,
        public int $milestones = 0,
        public int $streaks = 0,
    ) {
    }
}
