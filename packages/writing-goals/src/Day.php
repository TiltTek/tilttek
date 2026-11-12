<?php

declare(strict_types=1);

namespace Tilttek\WritingGoals;

use DateTimeImmutable;
use DateTimeZone;
use Tilttek\WritingGoals\Exception\InvalidGoal;

/**
 * A calendar date, without a time or a timezone, counted in days from 1970-01-01: two days compare as two integers.
 */
final readonly class Day
{
    private const int SECONDS_PER_DAY = 86_400;

    private function __construct(
        public int $number,
    ) {
    }

    /**
     * @param string $date a date written `YYYY-MM-DD`
     */
    public static function fromString(string $date): self
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));

        if (false === $parsed || $parsed->format('Y-m-d') !== $date) {
            throw InvalidGoal::because(\sprintf('"%s" is not a date written YYYY-MM-DD.', $date));
        }

        return new self(intdiv($parsed->getTimestamp(), self::SECONDS_PER_DAY));
    }

    /**
     * The day a number of days after 1970-01-01, or before it when negative.
     */
    public static function fromNumber(int $number): self
    {
        return new self($number);
    }

    /**
     * The date a moment falls on in a timezone.
     */
    public static function of(DateTimeImmutable $moment, DateTimeZone $timezone): self
    {
        return self::fromString($moment->setTimezone($timezone)->format('Y-m-d'));
    }

    public function toString(): string
    {
        return gmdate('Y-m-d', $this->number * self::SECONDS_PER_DAY);
    }

    public function plus(int $days): self
    {
        return new self($this->number + $days);
    }

    /**
     * The days from this one to another: 1 for the next day, negative for an earlier one.
     */
    public function daysUntil(self $other): int
    {
        return $other->number - $this->number;
    }

    public function isBefore(self $other): bool
    {
        return $this->number < $other->number;
    }

    public function isAfter(self $other): bool
    {
        return $this->number > $other->number;
    }

    public function equals(self $other): bool
    {
        return $this->number === $other->number;
    }

    /**
     * The ISO day of the week: 1 for Monday to 7 for Sunday.
     */
    public function weekday(): int
    {
        // 1970-01-01 was a Thursday.
        return (($this->number + 3) % 7 + 7) % 7 + 1;
    }
}
