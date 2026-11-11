<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Infrastructure\Event;

use Tilttek\Progression\LevelChange;

/**
 * A subject reached a higher level of a track: `#[AsEventListener] public function __invoke(LevelUp $event)`.
 */
final readonly class LevelUp
{
    public function __construct(
        public LevelChange $change,
    ) {
    }
}
