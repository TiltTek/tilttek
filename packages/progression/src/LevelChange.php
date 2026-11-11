<?php

declare(strict_types=1);

namespace Tilttek\Progression;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;

/**
 * A subject went from one level of a track to another in a season — the empty code for no season —, by an activity
 * or its cancellation.
 */
final readonly class LevelChange
{
    public function __construct(
        public string $track,
        public ResourceReference $subject,
        public string $season,
        public int $from,
        public int $to,
        public DateTimeImmutable $at,
        public string $activity,
    ) {
    }

    public function isUp(): bool
    {
        return $this->to > $this->from;
    }
}
