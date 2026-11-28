<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

use LogicException;
use Tilttek\FantasyCalendar\Contract\SeasonSource;
use Tilttek\FantasyCalendar\Exception\CalendarMismatch;
use Tilttek\FantasyCalendar\Exception\InvalidCalendar;
use Tilttek\FantasyCalendar\Exception\OutOfRange;

/**
 * An imaginary calendar: its months, its week, its leap years, its moons, its eras and its seasons. It turns a date
 * into a day number and back, gives the weekday, the phases of the moons, the year of an era and the season of any day,
 * with integers only, for any year within a million of year 0.
 */
final readonly class Calendar implements SeasonSource
{
    /**
     * @var non-empty-list<Month>
     */
    public array $months;

    /**
     * @var non-empty-list<string>
     */
    public array $weekdays;

    /**
     * @var list<Moon>
     */
    public array $moons;

    /**
     * @var list<Era>
     */
    public array $eras;

    /**
     * @var list<Season>
     */
    public array $seasons;

    /**
     * The days of a common year, and those a leap year adds.
     */
    private int $commonLength;

    private int $leapExtra;

    /**
     * The days outside the week in a common year, and those a leap year adds.
     */
    private int $outsideCommon;

    private int $outsideExtra;

    /**
     * The first and last day numbers within the bounds of the years.
     */
    private int $firstDay;

    private int $lastDay;

    /**
     * @param list<Month>  $months
     * @param list<string> $weekdays     the names of the days of the week, in order
     * @param int          $firstWeekday the weekday, from 0, of day 0 — the first day of year 1
     * @param list<Moon>   $moons
     * @param list<Era>    $eras         the eras by their first year, the one that counts backward first if any
     * @param list<Season> $seasons      the seasons by the day they start on, in the order of the year
     *
     * @throws InvalidCalendar when the calendar is not consistent
     */
    public function __construct(
        public string $id,
        public string $name,
        array $months,
        array $weekdays,
        public int $firstWeekday = 0,
        public LeapRule $leapRule = new LeapRule(),
        array $moons = [],
        array $eras = [],
        array $seasons = [],
        public ?string $climateZone = null,
    ) {
        Limits::identifier($id, 'A calendar identifier');
        Limits::label($name, 'A calendar name');
        if ([] === $months || \count($months) > Limits::MONTHS) {
            throw InvalidCalendar::because(\sprintf('A calendar has 1 to %d months.', Limits::MONTHS));
        }

        if ([] === $weekdays || \count($weekdays) > Limits::WEEKDAYS) {
            throw InvalidCalendar::because(\sprintf('A week has 1 to %d days.', Limits::WEEKDAYS));
        }

        foreach ($weekdays as $weekday) {
            Limits::label($weekday, 'A weekday name');
        }

        Limits::between($firstWeekday, 0, \count($weekdays) - 1, 'The weekday of day 0');
        if (null !== $climateZone) {
            Limits::identifier($climateZone, 'A climate zone');
        }

        $this->unique($months, 'month');
        $this->months = $months;
        $this->weekdays = $weekdays;
        $this->moons = $this->checkMoons($moons);
        $this->eras = $this->checkEras($eras);
        $this->seasons = $this->checkSeasons($seasons, $months);
        $commonLength = 0;
        $leapExtra = 0;
        $outsideCommon = 0;
        $outsideExtra = 0;
        foreach ($months as $month) {
            $commonLength += $month->days;
            $leapExtra += $month->leapDays;
            if (!$month->inWeek) {
                $outsideCommon += $month->days;
                $outsideExtra += $month->leapDays;
            }
        }

        if (0 === $commonLength) {
            throw InvalidCalendar::because('A common year has at least one day.');
        }

        $this->commonLength = $commonLength;
        $this->leapExtra = $leapExtra;
        $this->outsideCommon = $outsideCommon;
        $this->outsideExtra = $outsideExtra;
        $this->firstDay = $this->daysBefore(-Limits::YEAR);
        $this->lastDay = $this->daysBefore(Limits::YEAR + 1) - 1;
    }

    public function isLeap(int $year): bool
    {
        return $this->leapRule->isLeap($year);
    }

    /**
     * @throws OutOfRange when the year is out of bounds
     */
    public function yearLength(int $year): int
    {
        return $this->commonLength + ($this->isLeap(Limits::year($year)) ? $this->leapExtra : 0);
    }

    /**
     * @throws InvalidCalendar when the month does not exist
     * @throws OutOfRange      when the year is out of bounds
     */
    public function monthLength(int $year, int $month): int
    {
        return $this->month($month)->length($this->isLeap(Limits::year($year)));
    }

    /**
     * @throws InvalidCalendar when the month does not exist
     */
    public function month(int $month): Month
    {
        return $this->months[$month - 1] ?? throw InvalidCalendar::because(\sprintf('The calendar "%s" has months 1 to %d; %d is not one.', $this->id, \count($this->months), $month));
    }

    /**
     * The day of a date.
     *
     * @throws InvalidCalendar when the date does not exist in this calendar
     * @throws OutOfRange      when the year is out of bounds
     */
    public function day(int $year, int $month, int $day): Day
    {
        $length = $this->monthLength($year, $month);
        if ($day < 1 || $day > $length) {
            throw InvalidCalendar::because(\sprintf('The month %d of the year %d has days 1 to %d; %d is not one.', $month, $year, $length, $day));
        }

        $number = $this->daysBefore($year) + $day - 1;
        $leap = $this->isLeap($year);
        for ($before = 0; $before < $month - 1; ++$before) {
            $number += $this->months[$before]->length($leap);
        }

        return new Day($this->id, $number);
    }

    /**
     * @throws InvalidCalendar when the date does not exist in this calendar
     * @throws OutOfRange      when the year is out of bounds
     */
    public function dayOf(Date $date): Day
    {
        return $this->day($date->year, $date->month, $date->day);
    }

    /**
     * The date of a day.
     *
     * @throws CalendarMismatch when the day belongs to another calendar
     * @throws OutOfRange       when the day is out of the bounds of the years
     */
    public function date(Day $day): Date
    {
        $year = $this->yearOf($day);
        $offset = $day->number - $this->daysBefore($year);
        $leap = $this->isLeap($year);
        foreach ($this->months as $index => $month) {
            $length = $month->length($leap);
            if ($offset < $length) {
                return new Date($year, $index + 1, $offset + 1);
            }

            $offset -= $length;
        }

        // The year of the day holds it: daysBefore($year) <= number < daysBefore($year + 1).
        throw new LogicException('A day lies within its year.');
    }

    /**
     * The weekday of a day, from 0; null for a day outside the week.
     *
     * @throws CalendarMismatch when the day belongs to another calendar
     * @throws OutOfRange       when the day is out of the bounds of the years
     */
    public function weekday(Day $day): ?int
    {
        $date = $this->date($day);
        if (!$this->months[$date->month - 1]->inWeek) {
            return null;
        }

        $outside = $this->outsideBefore($date->year);
        $leap = $this->isLeap($date->year);
        for ($before = 0; $before < $date->month - 1; ++$before) {
            if (!$this->months[$before]->inWeek) {
                $outside += $this->months[$before]->length($leap);
            }
        }

        return Limits::mod($this->firstWeekday + $day->number - $outside, \count($this->weekdays));
    }

    /**
     * The name of the weekday of a day; null for a day outside the week.
     *
     * @throws CalendarMismatch when the day belongs to another calendar
     * @throws OutOfRange       when the day is out of the bounds of the years
     */
    public function weekdayName(Day $day): ?string
    {
        $weekday = $this->weekday($day);

        return null === $weekday ? null : $this->weekdays[$weekday];
    }

    /**
     * The phase of every moon on a day, by moon identifier.
     *
     * @return array<string, Phase>
     *
     * @throws CalendarMismatch when the day belongs to another calendar
     */
    public function moonPhases(Day $day): array
    {
        $this->own($day);
        $phases = [];
        foreach ($this->moons as $moon) {
            $phases[$moon->id] = $moon->phaseOn($day->number);
        }

        return $phases;
    }

    /**
     * A year as the eras number it.
     *
     * @throws OutOfRange when the year is out of bounds
     */
    public function yearInEra(int $year): EraYear
    {
        Limits::year($year);
        $backward = null;
        $current = null;
        foreach ($this->eras as $era) {
            if (null === $era->startYear) {
                $backward = $era;
            } elseif ($era->startYear <= $year) {
                $current = $era;
            } elseif (null === $current) {
                return null === $backward ? new EraYear(null, $year) : new EraYear($backward, $era->startYear - $year);
            }
        }

        return null === $current ? new EraYear(null, $year) : new EraYear($current, $year - (int) $current->startYear + 1);
    }

    /**
     * The year of the calendar that an era calls by the number given.
     *
     * @throws InvalidCalendar when the era is unknown, or the number is not one of its years
     */
    public function yearFromEra(string $era, int $year): int
    {
        $dated = array_values(array_filter($this->eras, static fn (Era $candidate): bool => !$candidate->countsBackward()));
        foreach ($this->eras as $candidate) {
            if ($candidate->id !== $era) {
                continue;
            }

            $index = array_search($candidate, $dated, true);
            $next = false === $index ? null : ($dated[$index + 1] ?? null);
            $last = null === $next ? \PHP_INT_MAX : (int) $next->startYear - (int) $candidate->startYear;
            if ($year < 1 || $year > $last) {
                throw InvalidCalendar::because(\sprintf('The era "%s" has no year %d.', $era, $year));
            }

            return null === $candidate->startYear ? (int) $dated[0]->startYear - $year : $candidate->startYear + $year - 1;
        }

        throw InvalidCalendar::because(\sprintf('The calendar "%s" has no era "%s".', $this->id, $era));
    }

    /**
     * The season of a day, from the seasons of the calendar; null when it has none.
     *
     * @throws CalendarMismatch when the day belongs to another calendar
     * @throws OutOfRange       when the day is out of the bounds of the years
     */
    public function seasonOn(Day $day): ?SeasonAt
    {
        if ([] === $this->seasons) {
            return null;
        }

        $date = $this->date($day);
        $index = \count($this->seasons) - 1;
        $year = $date->year - 1;
        foreach ($this->seasons as $position => $season) {
            if ($date->compareWithinYear(new Date($date->year, $season->month, $season->day)) >= 0) {
                $index = $position;
                $year = $date->year;
            }
        }

        $season = $this->seasons[$index];
        $start = $this->day($year, $season->month, $season->day);
        $next = $this->seasons[$index + 1] ?? null;
        $end = null === $next
            ? $this->day($year + 1, $this->seasons[0]->month, $this->seasons[0]->day)
            : $this->day($year, $next->month, $next->day);

        return new SeasonAt($season, $this->climateZone, $day->daysSince($start) + 1, $end->daysSince($start));
    }

    /**
     * The whole years from one day to another — an age —: one more each time the month and day of the first are
     * reached or passed; negative when the second comes first.
     *
     * @throws CalendarMismatch when a day belongs to another calendar
     * @throws OutOfRange       when a day is out of the bounds of the years
     */
    public function yearsBetween(Day $from, Day $to): int
    {
        $start = $this->date($from);
        $end = $this->date($to);
        $years = $end->year - $start->year;
        if ($years > 0 && $end->compareWithinYear($start) < 0) {
            return $years - 1;
        }

        if ($years < 0 && $end->compareWithinYear($start) > 0) {
            return $years + 1;
        }

        return $years;
    }

    /**
     * The days before the first day of a year, counted from day 0.
     */
    private function daysBefore(int $year): int
    {
        return ($year - 1) * $this->commonLength + $this->leapExtra * $this->leapsSinceYearOne($year);
    }

    /**
     * The days outside the week before the first day of a year, counted from day 0.
     */
    private function outsideBefore(int $year): int
    {
        return ($year - 1) * $this->outsideCommon + $this->outsideExtra * $this->leapsSinceYearOne($year);
    }

    private function leapsSinceYearOne(int $year): int
    {
        return $this->leapRule->leapsBefore($year) - $this->leapRule->leapsBefore(1);
    }

    /**
     * @throws CalendarMismatch when the day belongs to another calendar
     * @throws OutOfRange       when the day is out of the bounds of the years
     */
    private function yearOf(Day $day): int
    {
        $this->own($day);
        if ($day->number < $this->firstDay || $day->number > $this->lastDay) {
            throw OutOfRange::because(\sprintf('The day %d of the calendar "%s" lies outside the years -%d to %d.', $day->number, $this->id, Limits::YEAR, Limits::YEAR));
        }

        $period = $this->leapRule->period;
        $periodDays = $period * $this->commonLength + $this->leapRule->leapsPerPeriod * $this->leapExtra;
        $year = 1 + Limits::floorDiv($day->number * $period, $periodDays);
        while ($this->daysBefore($year) > $day->number) {
            --$year;
        }

        while ($this->daysBefore($year + 1) <= $day->number) {
            ++$year;
        }

        return $year;
    }

    /**
     * @throws CalendarMismatch when the day belongs to another calendar
     */
    private function own(Day $day): void
    {
        if ($day->calendar !== $this->id) {
            throw CalendarMismatch::between($day->calendar, $this->id);
        }
    }

    /**
     * @param list<Month|Moon|Era|Season> $items
     *
     * @throws InvalidCalendar when two items share an identifier
     */
    private function unique(array $items, string $what): void
    {
        $seen = [];
        foreach ($items as $item) {
            if (isset($seen[$item->id])) {
                throw InvalidCalendar::because(\sprintf('Two %ss are named "%s".', $what, $item->id));
            }

            $seen[$item->id] = true;
        }
    }

    /**
     * @param list<Moon> $moons
     *
     * @return list<Moon>
     *
     * @throws InvalidCalendar
     */
    private function checkMoons(array $moons): array
    {
        if (\count($moons) > Limits::MOONS) {
            throw InvalidCalendar::because(\sprintf('A calendar has at most %d moons.', Limits::MOONS));
        }

        $this->unique($moons, 'moon');

        return $moons;
    }

    /**
     * @param list<Era> $eras
     *
     * @return list<Era>
     *
     * @throws InvalidCalendar
     */
    private function checkEras(array $eras): array
    {
        if (\count($eras) > Limits::ERAS) {
            throw InvalidCalendar::because(\sprintf('A calendar has at most %d eras.', Limits::ERAS));
        }

        $this->unique($eras, 'era');
        $previous = null;
        foreach ($eras as $position => $era) {
            if (null === $era->startYear) {
                if (0 !== $position || 1 === \count($eras)) {
                    throw InvalidCalendar::because('An era that counts backward comes first, before a dated era.');
                }

                continue;
            }

            if (null !== $previous && $era->startYear <= $previous) {
                throw InvalidCalendar::because('The eras come in the order of their first years, each after the one before.');
            }

            $previous = $era->startYear;
        }

        return $eras;
    }

    /**
     * @param list<Season>          $seasons
     * @param non-empty-list<Month> $months
     *
     * @return list<Season>
     *
     * @throws InvalidCalendar
     */
    private function checkSeasons(array $seasons, array $months): array
    {
        if (\count($seasons) > Limits::SEASONS) {
            throw InvalidCalendar::because(\sprintf('A calendar has at most %d seasons.', Limits::SEASONS));
        }

        $this->unique($seasons, 'season');
        $previous = null;
        foreach ($seasons as $season) {
            $month = $months[$season->month - 1] ?? null;
            if (null === $month || $season->day < 1 || $season->day > $month->days) {
                throw InvalidCalendar::because(\sprintf('The season "%s" starts on a day every year has.', $season->id));
            }

            $start = new Date(0, $season->month, $season->day);
            if ($previous instanceof Date && $start->compareWithinYear($previous) <= 0) {
                throw InvalidCalendar::because('The seasons come in the order of the year, each after the one before.');
            }

            $previous = $start;
        }

        return $seasons;
    }
}
