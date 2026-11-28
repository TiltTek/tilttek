<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

use Tilttek\FantasyCalendar\Exception\InvalidCalendar;
use Tilttek\FantasyCalendar\Exception\OutOfRange;
use Tilttek\FantasyCalendar\Exception\UnknownCalendar;

/**
 * The calendars of a world, the links between them and which one is the parent of which: a day converts from any
 * calendar to any other a chain of links joins, and the reminders of a parent appear in its children. Immutable: each
 * addition returns a new registry.
 */
final readonly class Calendars
{
    /**
     * @param array<string, Calendar>           $calendars by identifier
     * @param array<string, array<string, int>> $shifts    the days added to go from one calendar to a linked one
     * @param array<string, string>             $parents   the parent of each child
     */
    private function __construct(private array $calendars = [], private array $shifts = [], private array $parents = [])
    {
    }

    public static function empty(): self
    {
        return new self();
    }

    /**
     * @throws InvalidCalendar when a calendar of that identifier is already registered
     * @throws OutOfRange      when the registry is full
     */
    public function with(Calendar $calendar): self
    {
        if (isset($this->calendars[$calendar->id])) {
            throw InvalidCalendar::because(\sprintf('A calendar "%s" is already registered.', $calendar->id));
        }

        if (\count($this->calendars) >= Limits::CALENDARS) {
            throw OutOfRange::because(\sprintf('A registry holds at most %d calendars.', Limits::CALENDARS));
        }

        return new self([...$this->calendars, $calendar->id => $calendar], $this->shifts, $this->parents);
    }

    /**
     * Two calendars joined by a day of the campaign they share.
     *
     * @throws UnknownCalendar when a calendar is not registered
     * @throws InvalidCalendar when the calendars are already joined
     */
    public function linked(Link $link): self
    {
        $first = $this->get($link->first->calendar)->id;
        $second = $this->get($link->second->calendar)->id;
        if (isset($this->shifts[$first][$second])) {
            throw InvalidCalendar::because(\sprintf('The calendars "%s" and "%s" are already linked.', $first, $second));
        }

        $shifts = $this->shifts;
        $shifts[$first][$second] = $link->shift();
        $shifts[$second][$first] = -$link->shift();

        return new self($this->calendars, $shifts, $this->parents);
    }

    /**
     * A child calendar under a parent, joined by a day they share — the first day of the link in the child, the second
     * in the parent: the reminders of the parent, and of its own parents, appear in the child.
     *
     * @throws UnknownCalendar when a calendar is not registered
     * @throws InvalidCalendar when the child already has a parent, the calendars are already linked, or the parent
     *                         descends from the child
     */
    public function withParent(Link $link): self
    {
        $child = $link->first->calendar;
        $parent = $link->second->calendar;
        $linked = $this->linked($link);
        if (isset($this->parents[$child])) {
            throw InvalidCalendar::because(\sprintf('The calendar "%s" already has a parent.', $child));
        }

        if (\in_array($child, $this->ancestors($parent), true)) {
            throw InvalidCalendar::because(\sprintf('The calendar "%s" descends from "%s": it cannot be its parent.', $parent, $child));
        }

        return new self($linked->calendars, $linked->shifts, [...$this->parents, $child => $parent]);
    }

    /**
     * @throws UnknownCalendar when no calendar of that identifier is registered
     */
    public function get(string $id): Calendar
    {
        return $this->calendars[$id] ?? throw UnknownCalendar::named($id);
    }

    /**
     * The parent of a calendar, its parent, and so on.
     *
     * @return list<string>
     *
     * @throws UnknownCalendar when the calendar is not registered
     */
    public function ancestors(string $id): array
    {
        $this->get($id);
        $ancestors = [];
        while (isset($this->parents[$id])) {
            $id = $this->parents[$id];
            $ancestors[] = $id;
        }

        return $ancestors;
    }

    /**
     * The same day in another calendar, through the shortest chain of links.
     *
     * @throws UnknownCalendar when a calendar is not registered, or no chain of at most {@see Limits::HOPS} links
     *                         joins them
     */
    public function convert(Day $day, string $to): Day
    {
        $this->get($day->calendar);
        $this->get($to);
        $shifts = [$day->calendar => 0];
        $frontier = [$day->calendar];
        for ($hop = 0; $hop < Limits::HOPS && !isset($shifts[$to]); ++$hop) {
            $next = [];
            foreach ($frontier as $from) {
                foreach ($this->shifts[$from] ?? [] as $neighbour => $shift) {
                    if (!isset($shifts[$neighbour])) {
                        $shifts[$neighbour] = $shifts[$from] + $shift;
                        $next[] = $neighbour;
                    }
                }
            }

            $frontier = $next;
        }

        if (!isset($shifts[$to])) {
            throw UnknownCalendar::unlinked($day->calendar, $to);
        }

        return new Day($to, $day->number + $shifts[$to]);
    }
}
