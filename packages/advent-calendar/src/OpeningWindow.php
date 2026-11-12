<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar;

use DateTimeImmutable;

/**
 * When a box may be opened: from the start of its day, included, until a moment, excluded.
 */
final readonly class OpeningWindow
{
    public function __construct(
        public DateTimeImmutable $from,
        public DateTimeImmutable $until,
    ) {
    }

    public function hasStarted(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->from;
    }

    public function hasEnded(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->until;
    }

    public function contains(DateTimeImmutable $moment): bool
    {
        return $this->hasStarted($moment) && !$this->hasEnded($moment);
    }
}
