<?php

declare(strict_types=1);

namespace Tilttek\MiniGames;

use DateTimeImmutable;
use DateTimeZone;
use Tilttek\MiniGames\Exception\InvalidMiniGame;

/**
 * A calendar day, `2026-11-24`, as the site's timezone sees it: the day of a daily game. Two days compare by their
 * text, which sorts as the calendar does.
 */
final readonly class Day
{
    private function __construct(
        public string $value,
    ) {
    }

    /**
     * The day a moment falls on in a timezone.
     */
    public static function of(DateTimeImmutable $moment, DateTimeZone $zone): self
    {
        return self::fromString($moment->setTimezone($zone)->format('Y-m-d'));
    }

    /**
     * @throws InvalidMiniGame when the text is not a day from 2000-01-01 to 9999-12-31, written `YYYY-MM-DD`
     */
    public static function fromString(string $day): self
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $day, new DateTimeZone('UTC'));

        if (false === $parsed || $parsed->format('Y-m-d') !== $day || $day < '2000-01-01') {
            throw InvalidMiniGame::because(\sprintf('"%s" is not a day written YYYY-MM-DD from 2000-01-01.', $day));
        }

        return new self($day);
    }

    /**
     * The number of the day counted from a first day, which is 1: the number of a daily game, shown in its share.
     *
     * @throws InvalidMiniGame when this day comes before the first
     */
    public function numberFrom(self $first): int
    {
        if ($this->isBefore($first)) {
            throw InvalidMiniGame::because(\sprintf('The day %s comes before the first day %s.', $this->value, $first->value));
        }

        return intdiv($this->midnight()->getTimestamp() - $first->midnight()->getTimestamp(), 86_400) + 1;
    }

    public function isBefore(self $other): bool
    {
        return $this->value < $other->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    private function midnight(): DateTimeImmutable
    {
        return new DateTimeImmutable($this->value, new DateTimeZone('UTC'));
    }
}
