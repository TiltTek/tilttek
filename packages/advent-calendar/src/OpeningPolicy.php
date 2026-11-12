<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar;

use Tilttek\AdventCalendar\Exception\InvalidCalendar;

/**
 * The opening policy a calendar declares: how long each box stays openable once its day has come. No policy opens a
 * box before its day. The default keeps a past box openable until the calendar closes.
 */
final readonly class OpeningPolicy
{
    public const int MAX_CATCH_UP_DAYS = 31;

    private function __construct(
        public PolicyKind $kind,
        public int $catchUpDays,
    ) {
    }

    public static function untilClosing(): self
    {
        return new self(PolicyKind::UntilClosing, 0);
    }

    public static function sameDay(): self
    {
        return new self(PolicyKind::SameDay, 0);
    }

    /**
     * @param int $days the days after its own a box stays openable, 1 to 31
     */
    public static function catchUp(int $days): self
    {
        if ($days < 1 || $days > self::MAX_CATCH_UP_DAYS) {
            throw InvalidCalendar::because(\sprintf('A catch-up lasts 1 to %d days; %d is not one.', self::MAX_CATCH_UP_DAYS, $days));
        }

        return new self(PolicyKind::CatchUp, $days);
    }

    /**
     * The days after its own a box stays openable, before the closing caps it; null for as long as the calendar is
     * open.
     */
    public function daysAfter(): ?int
    {
        return match ($this->kind) {
            PolicyKind::UntilClosing => null,
            PolicyKind::SameDay => 0,
            PolicyKind::CatchUp => $this->catchUpDays,
        };
    }
}
