<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Period;

use DateTimeImmutable;
use DateTimeZone;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;

/**
 * The periods of a board: which one a moment falls in, which one a code names, and which one ended last. Calendar
 * periods follow each other without a gap, in the schedule's timezone; seasons are the application's, never overlap,
 * and may leave time between them, which counts for no period.
 */
final readonly class Schedule
{
    /**
     * @var array<string, Period> the seasons by code, sorted by start
     */
    public array $seasons;

    public function __construct(
        public PeriodKind $kind,
        public DateTimeZone $timezone = new DateTimeZone('UTC'),
        Period ...$seasons,
    ) {
        if ((PeriodKind::Season === $kind) === ([] === $seasons)) {
            throw InvalidLeaderboard::because('A schedule by seasons names at least one season, and only a schedule by seasons names any.');
        }

        usort($seasons, static fn (Period $a, Period $b): int => $a->start <=> $b->start);
        $byCode = [];
        $previous = null;

        foreach ($seasons as $season) {
            if (!$season->end instanceof DateTimeImmutable) {
                throw InvalidLeaderboard::because(\sprintf('The season "%s" has a start and an end.', $season->code));
            }

            if (isset($byCode[$season->code])) {
                throw InvalidLeaderboard::because(\sprintf('Two seasons are coded "%s".', $season->code));
            }

            if ($previous instanceof Period && $season->start < $previous->end) {
                throw InvalidLeaderboard::because(\sprintf('The seasons "%s" and "%s" overlap.', $previous->code, $season->code));
            }

            $byCode[$season->code] = $previous = $season;
        }

        $this->seasons = $byCode;
    }

    /**
     * The period the moment falls in; null between two seasons.
     */
    public function at(DateTimeImmutable $moment): ?Period
    {
        return match ($this->kind) {
            PeriodKind::Ever => Period::ever(),
            PeriodKind::Window => Period::window(),
            PeriodKind::Season => array_find($this->seasons, static fn (Period $season): bool => $season->holds($moment)),
            default => $this->calendar($moment),
        };
    }

    /**
     * The period the code names, or null when the schedule has none by that code.
     */
    public function named(string $code): ?Period
    {
        if (PeriodKind::Ever === $this->kind) {
            return Period::EVER === $code ? Period::ever() : null;
        }

        if (PeriodKind::Window === $this->kind) {
            return Period::WINDOW === $code ? Period::window() : null;
        }

        if (PeriodKind::Season === $this->kind) {
            return $this->seasons[$code] ?? null;
        }

        $start = $this->start($code);

        if (!$start instanceof DateTimeImmutable) {
            return null;
        }

        [$found, $begins, $ends] = $this->bounds($start);

        return $found === $code && $begins->getTimestamp() === $start->getTimestamp() ? new Period($found, $begins, $ends) : null;
    }

    /**
     * The period that ended last at that moment — the one before the moment's, or the last season over —; null since
     * ever, over a sliding window, or before the first season ends.
     */
    public function previous(DateTimeImmutable $moment): ?Period
    {
        return match ($this->kind) {
            PeriodKind::Ever, PeriodKind::Window => null,
            PeriodKind::Season => array_last(array_filter($this->seasons, static fn (Period $season): bool => $season->isOverAt($moment))),
            default => $this->calendar($this->bounds($moment)[1]->modify('-1 second')),
        };
    }

    private function calendar(DateTimeImmutable $moment): Period
    {
        return new Period(...$this->bounds($moment));
    }

    /**
     * The code, the start and the end of the calendar period the moment falls in.
     *
     * @return array{string, DateTimeImmutable, DateTimeImmutable}
     */
    private function bounds(DateTimeImmutable $moment): array
    {
        $day = $moment->setTimezone($this->timezone)->setTime(0, 0);
        $year = (int) $day->format('Y');
        $month = (int) $day->format('n');

        [$code, $start, $length] = match ($this->kind) {
            PeriodKind::Day => [$day->format('Y-m-d'), $day, '+1 day'],
            PeriodKind::Week => [($start = $day->modify('monday this week'))->format('o-\wW'), $start, '+1 week'],
            PeriodKind::Month => [$day->format('Y-m'), $day->setDate($year, $month, 1), '+1 month'],
            PeriodKind::Quarter => [\sprintf('%04d-q%d', $year, $quarter = intdiv($month + 2, 3)), $day->setDate($year, 3 * $quarter - 2, 1), '+3 months'],
            default => [$day->format('Y'), $day->setDate($year, 1, 1), '+1 year'],
        };

        return [$code, $start, $start->modify($length)];
    }

    /**
     * The start of the calendar period the code would name, its numbers read loosely: the period that starts there
     * is compared with the code, so `2026-13` or `2026-w54` names none.
     */
    private function start(string $code): ?DateTimeImmutable
    {
        $midnight = new DateTimeImmutable('2000-01-01', $this->timezone);

        return match ($this->kind) {
            PeriodKind::Day => 1 === preg_match('/(?<year>\d+)\D+(?<month>\d+)\D+(?<day>\d+)/', $code, $parts) ? $midnight->setDate((int) $parts['year'], (int) $parts['month'], (int) $parts['day']) : null,
            PeriodKind::Week => 1 === preg_match('/(?<year>\d+)\D+(?<week>\d+)/', $code, $parts) ? $midnight->setISODate((int) $parts['year'], (int) $parts['week']) : null,
            PeriodKind::Month => 1 === preg_match('/(?<year>\d+)\D+(?<month>\d+)/', $code, $parts) ? $midnight->setDate((int) $parts['year'], (int) $parts['month'], 1) : null,
            PeriodKind::Quarter => 1 === preg_match('/(?<year>\d+)\D+(?<quarter>\d+)/', $code, $parts) ? $midnight->setDate((int) $parts['year'], 3 * (int) $parts['quarter'] - 2, 1) : null,
            default => 1 === preg_match('/(?<year>\d+)/', $code, $parts) ? $midnight->setDate((int) $parts['year'], 1, 1) : null,
        };
    }
}
