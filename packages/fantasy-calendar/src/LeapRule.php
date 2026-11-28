<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

use Tilttek\FantasyCalendar\Exception\InvalidCalendar;

/**
 * Which years are leap: the cycles in order, the last one a year matches deciding — every 4 years, but not every 100,
 * but every 400. The pattern repeats every period, which makes counting leap years over a million years exact and fast.
 */
final readonly class LeapRule
{
    /**
     * @var list<LeapCycle>
     */
    public array $cycles;

    /**
     * The years after which the pattern repeats.
     */
    public int $period;

    /**
     * The leap years of one period.
     */
    public int $leapsPerPeriod;

    /**
     * @var list<int> the leap years among the first n years of a period, for n from 0 to the period
     */
    private array $prefix;

    /**
     * @param list<LeapCycle> $cycles
     *
     * @throws InvalidCalendar when there are too many cycles or their period is too long
     */
    public function __construct(array $cycles = [])
    {
        if (\count($cycles) > Limits::CYCLES) {
            throw InvalidCalendar::because(\sprintf('A leap rule has at most %d cycles.', Limits::CYCLES));
        }

        $period = 1;
        foreach ($cycles as $cycle) {
            $period = intdiv($period * $cycle->every, $this->gcd($period, $cycle->every));
            if ($period > Limits::LEAP_PERIOD) {
                throw InvalidCalendar::because(\sprintf('The leap cycles repeat within %d years.', Limits::LEAP_PERIOD));
            }
        }

        $this->cycles = $cycles;
        $this->period = $period;
        $prefix = [0];
        for ($year = 0; $year < $period; ++$year) {
            $prefix[] = $prefix[$year] + ($this->isLeap($year) ? 1 : 0);
        }

        $this->prefix = $prefix;
        $this->leapsPerPeriod = $prefix[$period];
    }

    public function isLeap(int $year): bool
    {
        $leap = false;
        foreach ($this->cycles as $cycle) {
            if ($cycle->matches($year)) {
                $leap = $cycle->leap;
            }
        }

        return $leap;
    }

    /**
     * The leap years from year 0 up to the year given, that one left out; negative for a year before 0.
     */
    public function leapsBefore(int $year): int
    {
        $periods = Limits::floorDiv($year, $this->period);

        return $periods * $this->leapsPerPeriod + $this->prefix[$year - $periods * $this->period];
    }

    private function gcd(int $a, int $b): int
    {
        while (0 !== $b) {
            [$a, $b] = [$b, $a % $b];
        }

        return $a;
    }
}
