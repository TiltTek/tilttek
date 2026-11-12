<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Tilttek\AdventCalendar\Exception\InvalidCalendar;

/**
 * An advent calendar: one box per day from its first date, one surprise per box, the timezone its days are counted in,
 * the policy that says how long a box stays openable, and the date after which it closes. Day 1 is the first date.
 */
final readonly class Calendar
{
    public const int MAX_DAYS = 92;

    public const int MAX_DAYS_AFTER_LAST_BOX = 366;

    private const int SECONDS_PER_DAY = 86_400;

    public DateTimeImmutable $closesAt;

    /**
     * @param list<Surprise> $surprises
     */
    private function __construct(
        public string $id,
        public DateTimeZone $timezone,
        public string $firstDate,
        public string $closingDate,
        public array $surprises,
        public OpeningPolicy $policy,
        private int $firstDay,
        int $closingDay,
    ) {
        $this->closesAt = $this->startOf($closingDay + 1);
    }

    /**
     * @param string         $id          1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit
     * @param string         $timezone    the timezone the days are counted in, `Europe/Paris`
     * @param string         $firstDate   the date of box 1, `2026-12-01`
     * @param list<Surprise> $surprises   one per box, in the order of the days: 1 to 92
     * @param string|null    $closingDate the last date a box may be opened, the date of the last box when null; up to 366 days after it
     */
    public static function of(string $id, string $timezone, string $firstDate, array $surprises, ?OpeningPolicy $policy = null, ?string $closingDate = null): self
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $id)) {
            throw InvalidCalendar::because(\sprintf('A calendar identifier is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit; "%s" is not.', $id));
        }

        try {
            $zone = new DateTimeZone($timezone);
        } catch (Exception) {
            throw InvalidCalendar::because(\sprintf('"%s" is not a timezone.', $timezone));
        }

        $count = \count($surprises);

        if ($count < 1 || $count > self::MAX_DAYS) {
            throw InvalidCalendar::because(\sprintf('A calendar holds 1 to %d boxes; %d were given.', self::MAX_DAYS, $count));
        }

        $first = self::dayNumber($firstDate);
        $last = $first + $count - 1;
        $closing = null === $closingDate ? $last : self::dayNumber($closingDate);

        if ($closing < $last || $closing > $last + self::MAX_DAYS_AFTER_LAST_BOX) {
            throw InvalidCalendar::because(\sprintf('A calendar closes on the date of its last box or up to %d days after it; %s is not one of those dates.', self::MAX_DAYS_AFTER_LAST_BOX, $closingDate));
        }

        return new self($id, $zone, $firstDate, self::dateFrom($closing), $surprises, $policy ?? OpeningPolicy::untilClosing(), $first, $closing);
    }

    public function days(): int
    {
        return \count($this->surprises);
    }

    public function surprise(int $day): Surprise
    {
        return $this->surprises[$this->index($day)];
    }

    /**
     * The date of a box, `2026-12-24`.
     */
    public function dateOf(int $day): string
    {
        return self::dateFrom($this->firstDay + $this->index($day));
    }

    /**
     * The window a box may be opened in: from the start of its day, in the calendar's timezone, until the policy or the
     * closing ends it.
     */
    public function window(int $day): OpeningWindow
    {
        $number = $this->firstDay + $this->index($day);
        $after = $this->policy->daysAfter();
        $until = null === $after ? $this->closesAt : min($this->startOf($number + $after + 1), $this->closesAt);

        return new OpeningWindow($this->startOf($number), $until);
    }

    /**
     * The box whose day a moment falls on, in the calendar's timezone; null before the first day or after the last.
     */
    public function dayAt(DateTimeImmutable $moment): ?int
    {
        $day = self::dayNumber($moment->setTimezone($this->timezone)->format('Y-m-d')) - $this->firstDay + 1;

        return $day >= 1 && $day <= $this->days() ? $day : null;
    }

    public function isClosed(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->closesAt;
    }

    private function index(int $day): int
    {
        if ($day < 1 || $day > $this->days()) {
            throw InvalidCalendar::because(\sprintf('The calendar "%s" has boxes 1 to %d; %d is not one.', $this->id, $this->days(), $day));
        }

        return $day - 1;
    }

    /**
     * The first moment of a day, by its number, in the calendar's timezone: midnight, or the first moment after it on a
     * date whose midnight a change of time skips.
     */
    private function startOf(int $dayNumber): DateTimeImmutable
    {
        return new DateTimeImmutable(self::dateFrom($dayNumber), $this->timezone);
    }

    /**
     * The days from 1970-01-01 to a date written `Y-m-d`.
     */
    private static function dayNumber(string $date): int
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date, new DateTimeZone('UTC'));

        if (false === $parsed || $parsed->format('Y-m-d') !== $date) {
            throw InvalidCalendar::because(\sprintf('"%s" is not a date written YYYY-MM-DD.', $date));
        }

        return intdiv($parsed->getTimestamp(), self::SECONDS_PER_DAY);
    }

    private static function dateFrom(int $dayNumber): string
    {
        return gmdate('Y-m-d', $dayNumber * self::SECONDS_PER_DAY);
    }
}
