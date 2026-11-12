<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar;

use DateTimeImmutable;

/**
 * A box as one member sees it: its day and date, its state, its window, and its surprise once opened — never before.
 */
final readonly class BoxView
{
    public function __construct(
        public int $day,
        public string $date,
        public BoxState $state,
        public OpeningWindow $window,
        public ?Surprise $surprise = null,
        public ?DateTimeImmutable $openedAt = null,
    ) {
    }

    public function isOpenable(): bool
    {
        return BoxState::Openable === $this->state;
    }
}
