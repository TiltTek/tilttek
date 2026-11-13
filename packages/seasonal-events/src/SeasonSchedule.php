<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Phil\Recurrence\Exception\RecurrenceException;
use Phil\Recurrence\Frequency;
use Phil\Recurrence\Recurrence;
use Phil\Recurrence\RecurrenceRule;
use Tilttek\SeasonalEvents\Exception\InvalidSeason;

/**
 * When a season comes, in its timezone: once, between two dates, or each year for a number of days from a date an RFC
 * 5545 yearly rule gives — the 15th of December, the fourth Thursday of November. Its days start at midnight on the
 * wall clock of the timezone, across changes of time.
 */
final readonly class SeasonSchedule
{
    /** The longest season, in days: half a year, so that a season that comes each year never meets its next time. */
    public const int MAX_DAYS = 183;

    /** How far ahead the next time of a season is looked for, per year of its interval: eight years hold a 29th of February. */
    private const int LOOKAHEAD_DAYS = 8 * 366;

    /** The rule of a season that comes back on the same date each year. */
    public const string EACH_YEAR = 'FREQ=YEARLY';

    private ?Recurrence $recurrence;

    /** The interval in years of a rule that only says how often, every year by default; null for any other rule. */
    private ?int $everyYears;

    private function __construct(
        public DateTimeZone $timezone,
        public string $firstDate,
        public int $days,
        public ?RecurrenceRule $rule,
    ) {
        $this->recurrence = $rule instanceof RecurrenceRule ? Recurrence::of(Days::start(Days::number($firstDate), $timezone), $rule) : null;
        $this->everyYears = $rule instanceof RecurrenceRule && null === $rule->count && !$rule->until instanceof DateTimeImmutable && [] === [...$rule->byDay, ...$rule->byMonthDay, ...$rule->byMonth, ...$rule->bySetPos] ? $rule->interval : null;
    }

    /**
     * A season that comes once.
     *
     * @param string $timezone  the timezone its days are counted in, `America/Toronto`
     * @param string $firstDate its first day, `2026-10-15`
     * @param string $lastDate  its last day, included, up to 182 days after the first
     */
    public static function once(string $timezone, string $firstDate, string $lastDate): self
    {
        $days = Days::number($lastDate) - Days::number($firstDate) + 1;

        return new self(self::zone($timezone), $firstDate, self::length($days), null);
    }

    /**
     * A season that comes each year.
     *
     * @param string $timezone  the timezone its days are counted in
     * @param string $firstDate the first day of its first time, which the rule starts from, `2026-12-15`
     * @param int    $days      how many days it lasts each time, 1 to 183
     * @param string $rule      a yearly RFC 5545 rule, `FREQ=YEARLY;BYMONTH=11;BYDAY=4TH`; the same date each year by default
     */
    public static function yearly(string $timezone, string $firstDate, int $days, string $rule = self::EACH_YEAR): self
    {
        $zone = self::zone($timezone);
        Days::number($firstDate);

        try {
            $parsed = RecurrenceRule::parse($rule, $zone);
        } catch (RecurrenceException $recurrenceException) {
            throw InvalidSeason::because(\sprintf('The rule of a season is not read: %s', $recurrenceException->getMessage()), $recurrenceException);
        }

        if (Frequency::Yearly !== $parsed->frequency) {
            throw InvalidSeason::because(\sprintf('A season comes back each year: its rule is yearly; "%s" is not.', $rule));
        }

        return new self($zone, $firstDate, self::length($days), $parsed);
    }

    public function isYearly(): bool
    {
        return $this->recurrence instanceof Recurrence;
    }

    /**
     * The time of the season a moment falls in, or null when the season is not on then. When two times of a season
     * meet, the later one holds the moment.
     */
    public function periodAt(DateTimeImmutable $moment): ?Period
    {
        $day = Days::of($moment, $this->timezone);

        foreach (array_reverse($this->startsBetween($day - $this->days + 1, $day)) as $start) {
            $period = $this->period($start);

            if ($period->contains($moment)) {
                return $period;
            }
        }

        return null;
    }

    /**
     * The first time of the season that starts after a moment, or null when it never comes again.
     */
    public function nextPeriod(DateTimeImmutable $after): ?Period
    {
        if (!$this->recurrence instanceof Recurrence) {
            $period = $this->period(Days::number($this->firstDate));

            return $period->from > $after ? $period : null;
        }

        if (null !== $this->everyYears) {
            $day = Days::of($after, $this->timezone);

            foreach ($this->sameDateBetween($this->everyYears, $day, min($day + self::LOOKAHEAD_DAYS * $this->everyYears, Days::LAST_DAY)) as $start) {
                $period = $this->period($start);

                if ($period->from > $after) {
                    return $period;
                }
            }

            return null;
        }

        $next = $this->recurrence->next($after);

        return $next instanceof DateTimeImmutable ? $this->period(Days::of($next, $this->timezone)) : null;
    }

    /**
     * The times of the season that start from one date through another, in order, at most so many.
     *
     * @return list<Period>
     */
    public function periods(string $fromDate, string $throughDate, int $limit = 100): array
    {
        $periods = array_map($this->period(...), $this->startsBetween(Days::number($fromDate), Days::number($throughDate)));

        return \array_slice($periods, 0, max(0, $limit));
    }

    /**
     * @return array{timezone: string, first_date: string, days: int, rule: ?string}
     */
    public function toArray(): array
    {
        return ['timezone' => $this->timezone->getName(), 'first_date' => $this->firstDate, 'days' => $this->days, 'rule' => $this->rule?->__toString()];
    }

    /**
     * The numbers of the days a time of the season starts on, from one day through another.
     *
     * @return list<int>
     */
    private function startsBetween(int $fromDay, int $throughDay): array
    {
        if (!$this->recurrence instanceof Recurrence) {
            $first = Days::number($this->firstDate);

            return $first >= $fromDay && $first <= $throughDay ? [$first] : [];
        }

        if (null !== $this->everyYears) {
            return $this->sameDateBetween($this->everyYears, $fromDay, $throughDay);
        }

        $starts = $this->recurrence->between(Days::start($fromDay, $this->timezone), Days::start($throughDay, $this->timezone));

        return array_map(fn (DateTimeImmutable $start): int => Days::of($start, $this->timezone), $starts);
    }

    /**
     * The starts of a season that comes back on the date of its first day every so many years, counted without walking
     * the rule: a 29th of February comes back in leap years only, as RFC 5545 skips the dates that do not exist.
     *
     * @return list<int>
     */
    private function sameDateBetween(int $interval, int $fromDay, int $throughDay): array
    {
        [$firstYear, $month, $day] = array_map(intval(...), explode('-', $this->firstDate));
        $starts = [];

        for ($year = max($firstYear, Days::year($fromDay)); $year <= Days::year($throughDay); ++$year) {
            if (0 !== ($year - $firstYear) % $interval || !checkdate($month, $day, $year)) {
                continue;
            }

            $start = Days::fromCivil($year, $month, $day);

            if ($start >= $fromDay && $start <= $throughDay) {
                $starts[] = $start;
            }
        }

        return $starts;
    }

    private function period(int $firstDay): Period
    {
        return new Period(Days::date($firstDay), Days::start($firstDay, $this->timezone), Days::start($firstDay + $this->days, $this->timezone));
    }

    private static function zone(string $timezone): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone);
        } catch (Exception $exception) {
            throw InvalidSeason::because(\sprintf('"%s" is not a timezone.', $timezone), $exception);
        }
    }

    private static function length(int $days): int
    {
        if ($days < 1 || $days > self::MAX_DAYS) {
            throw InvalidSeason::because(\sprintf('A season lasts 1 to %d days; %d is not one.', self::MAX_DAYS, $days));
        }

        return $days;
    }
}
