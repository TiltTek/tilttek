<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;

/**
 * A box a member opened: the calendar, the member, the day and the moment it was first opened.
 */
final readonly class Opening
{
    public function __construct(
        public string $calendar,
        public ResourceReference $member,
        public int $day,
        public DateTimeImmutable $openedAt,
    ) {
    }

    /**
     * @return array{calendar: string, member: string, day: int, opened_at: string}
     */
    public function toArray(): array
    {
        return ['calendar' => $this->calendar, 'member' => $this->member->toString(), 'day' => $this->day, 'opened_at' => $this->openedAt->format(DateTimeInterface::ATOM)];
    }
}
