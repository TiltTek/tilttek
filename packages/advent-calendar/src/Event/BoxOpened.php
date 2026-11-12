<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar\Event;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Tilttek\AdventCalendar\Surprise;

/**
 * Dispatched once, when a member first opens a box: the application grants the surprise — credits the points, awards
 * the badge, reveals the content, hands out a code — and may start a celebration. It carries references only.
 */
final readonly class BoxOpened
{
    public function __construct(
        public string $calendar,
        public ResourceReference $member,
        public int $day,
        public Surprise $surprise,
        public DateTimeImmutable $openedAt,
    ) {
    }

    /**
     * A key unique to this opening, for an application that grants the surprise through a ledger refusing duplicates.
     */
    public function idempotencyKey(): string
    {
        return \sprintf('advent:%s:%s:%d', $this->calendar, $this->member->toString(), $this->day);
    }
}
