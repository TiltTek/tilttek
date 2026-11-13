<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents;

use DateTimeImmutable;
use Tilttek\SeasonalEvents\Exception\InvalidSeason;

/**
 * A season of the site: when it comes, the quests, items and decorations it brings while it lasts, and the events
 * inside it. Its identifier is the site's and stays the same from one year to the next.
 */
final readonly class Season
{
    /** A stable identifier: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit. */
    public const string IDENTIFIER = '/^[a-z0-9][a-z0-9_.-]{0,63}$/D';

    public const int MAX_CONTENTS = 200;

    public const int MAX_EVENTS = 50;

    /** @var array<string, SeasonalEvent> */
    private array $byId;

    /**
     * @param list<Content>       $contents what the season brings while it lasts, 0 to 200
     * @param list<SeasonalEvent> $events   its events, 0 to 50, each starting inside it
     */
    public function __construct(
        public string $id,
        public SeasonSchedule $schedule,
        public array $contents = [],
        public array $events = [],
    ) {
        if (1 !== preg_match(self::IDENTIFIER, $id)) {
            throw InvalidSeason::because(\sprintf('A season identifier is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit; "%s" is not.', $id));
        }

        if (\count($contents) > self::MAX_CONTENTS || \count($events) > self::MAX_EVENTS) {
            throw InvalidSeason::because(\sprintf('A season brings at most %d contents and holds at most %d events.', self::MAX_CONTENTS, self::MAX_EVENTS));
        }

        $byId = [];

        foreach ($events as $event) {
            if (isset($byId[$event->id])) {
                throw InvalidSeason::because(\sprintf('The season "%s" holds two events "%s".', $id, $event->id));
            }

            if ($event->fromDay > $schedule->days) {
                throw InvalidSeason::because(\sprintf('The season "%s" lasts %d days; its event "%s" starts on day %d.', $id, $schedule->days, $event->id, $event->fromDay));
            }

            $byId[$event->id] = $event;
        }

        $this->byId = $byId;
    }

    public function event(string $id): ?SeasonalEvent
    {
        return $this->byId[$id] ?? null;
    }

    /**
     * The time of the season a moment falls in, or null.
     */
    public function periodAt(DateTimeImmutable $moment): ?Period
    {
        return $this->schedule->periodAt($moment);
    }

    /**
     * When an event runs in one time of the season: from the start of its first day to the start of the day after its
     * last, never past the season's end.
     */
    public function eventIn(SeasonalEvent $event, Period $period): ActiveEvent
    {
        $first = Days::number($period->code) + $event->fromDay - 1;
        $from = Days::start($first, $this->schedule->timezone);
        $until = null === $event->days ? $period->until : min(Days::start($first + $event->days, $this->schedule->timezone), $period->until);

        return new ActiveEvent($this, $event, $period, $from, $until);
    }

    /**
     * The events of one time of the season that run on a day, and those announced on it, by the number of the day in
     * the season's timezone: a day's answer holds for each of its moments, since events start and end at midnight.
     *
     * @return array{list<ActiveEvent>, list<ActiveEvent>} the events running, then the events announced
     */
    public function eventsOn(Period $period, int $day): array
    {
        $periodFirst = Days::number($period->code);
        $periodLast = $periodFirst + $this->schedule->days - 1;
        $running = [];
        $announced = [];

        foreach ($this->events as $event) {
            $first = $periodFirst + $event->fromDay - 1;
            $last = null === $event->days ? $periodLast : min($first + $event->days - 1, $periodLast);

            if ($day >= $first && $day <= $last) {
                $running[] = $this->eventIn($event, $period);
            } elseif ($day < $first && $day >= $first - $event->announceDays) {
                $announced[] = $this->eventIn($event, $period);
            }
        }

        return [$running, $announced];
    }
}
