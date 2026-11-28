<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

use DateTimeInterface;

/**
 * The current day of a world: the day it was set to, the real moment it was set, and the pace it moves at since. The
 * application keeps these three values and gives the real time each time it asks; nothing is kept between two calls.
 */
final readonly class CurrentDate
{
    /**
     * @param int $since the real moment the day was set, in seconds since the Unix epoch
     */
    public function __construct(public Day $day, public int $since, public Pace $pace)
    {
    }

    /**
     * The current day at a real moment: the day set, plus the whole days of the world passed since at the pace; the day
     * set itself for a moment before it was set.
     */
    public function today(DateTimeInterface $now): Day
    {
        $elapsed = $now->getTimestamp() - $this->since;
        if ($this->pace->isManual() || $elapsed <= 0) {
            return $this->day;
        }

        return $this->day->plus(Limits::floorDiv($elapsed * $this->pace->worldDays, $this->pace->realDays * 86_400));
    }

    /**
     * The game master moves the date forward, or back with a negative number, from the current day.
     */
    public function advancedBy(int $days, DateTimeInterface $now): self
    {
        return new self($this->today($now)->plus($days), $now->getTimestamp(), $this->pace);
    }

    /**
     * The game master sets the date to a day.
     */
    public function setTo(Day $day, DateTimeInterface $now): self
    {
        return new self($day, $now->getTimestamp(), $this->pace);
    }

    /**
     * The date moves at another pace from now, keeping the current day.
     */
    public function atPace(Pace $pace, DateTimeInterface $now): self
    {
        return new self($this->today($now), $now->getTimestamp(), $pace);
    }
}
