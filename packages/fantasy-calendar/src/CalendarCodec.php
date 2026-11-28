<?php

declare(strict_types=1);

namespace Tilttek\FantasyCalendar;

use Closure;
use Tilttek\FantasyCalendar\Exception\InvalidCalendar;

/**
 * Writes a calendar to an array — for JSON or YAML, for a calendar to copy — and reads one back, refusing anything it
 * does not know, with the place of the refused value.
 */
final class CalendarCodec
{
    private const array KEYS = ['id', 'name', 'months', 'weekdays', 'firstWeekday', 'leap', 'moons', 'eras', 'seasons', 'climateZone'];

    /**
     * @return array{id: string, name: string, months: list<array{id: string, name: string, days: int, leapDays: int, inWeek: bool}>, weekdays: list<string>, firstWeekday: int, leap: list<array{every: int, offset: int, leap: bool}>, moons: list<array{id: string, name: string, cycle: string|array{int, int}, newMoon: int}>, eras: list<array{id: string, name: string, start: int|null}>, seasons: list<array{id: string, name: string, month: string, day: int}>, climateZone: string|null}
     */
    public static function toArray(Calendar $calendar): array
    {
        return [
            'id' => $calendar->id,
            'name' => $calendar->name,
            'months' => array_map(static fn (Month $month): array => ['id' => $month->id, 'name' => $month->name, 'days' => $month->days, 'leapDays' => $month->leapDays, 'inWeek' => $month->inWeek], $calendar->months),
            'weekdays' => $calendar->weekdays,
            'firstWeekday' => $calendar->firstWeekday,
            'leap' => array_map(static fn (LeapCycle $cycle): array => ['every' => $cycle->every, 'offset' => $cycle->offset, 'leap' => $cycle->leap], $calendar->leapRule->cycles),
            'moons' => array_map(static fn (Moon $moon): array => ['id' => $moon->id, 'name' => $moon->name, 'cycle' => $moon->decimalCycle() ?? [$moon->cycleNumerator, $moon->cycleDenominator], 'newMoon' => $moon->newMoon], $calendar->moons),
            'eras' => array_map(static fn (Era $era): array => ['id' => $era->id, 'name' => $era->name, 'start' => $era->startYear], $calendar->eras),
            'seasons' => array_map(static fn (Season $season): array => ['id' => $season->id, 'name' => $season->name, 'month' => $calendar->months[$season->month - 1]->id, 'day' => $season->day], $calendar->seasons),
            'climateZone' => $calendar->climateZone,
        ];
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @throws InvalidCalendar when the array is not a calendar
     */
    public static function fromArray(array $data): Calendar
    {
        foreach (array_keys($data) as $key) {
            if (!\in_array($key, self::KEYS, true)) {
                throw InvalidCalendar::at((string) $key, 'is not a key of a calendar');
            }
        }

        $months = [];
        foreach (self::list($data, 'months', true) as $index => $month) {
            $place = 'months.'.$index;
            $entry = self::map($month, $place, ['id', 'name', 'days', 'leapDays', 'inWeek']);
            $months[] = self::build($place, static fn (): Month => new Month(
                self::string($entry, 'id', $place),
                self::string($entry, 'name', $place),
                self::int($entry, 'days', $place),
                self::int($entry, 'leapDays', $place, 0),
                self::bool($entry, 'inWeek', $place, true),
            ));
        }

        $weekdays = [];
        foreach (self::list($data, 'weekdays', true) as $index => $weekday) {
            if (!\is_string($weekday)) {
                throw InvalidCalendar::at('weekdays.'.$index, 'is a text');
            }

            $weekdays[] = $weekday;
        }

        $cycles = [];
        foreach (self::list($data, 'leap', false) as $index => $cycle) {
            $place = 'leap.'.$index;
            $entry = self::map($cycle, $place, ['every', 'offset', 'leap']);
            $cycles[] = self::build($place, static fn (): LeapCycle => new LeapCycle(self::int($entry, 'every', $place), self::int($entry, 'offset', $place, 0), self::bool($entry, 'leap', $place, true)));
        }

        $moons = [];
        foreach (self::list($data, 'moons', false) as $index => $moon) {
            $place = 'moons.'.$index;
            $entry = self::map($moon, $place, ['id', 'name', 'cycle', 'newMoon']);
            $moons[] = self::build($place, static fn (): Moon => self::moon($entry, $place));
        }

        $eras = [];
        foreach (self::list($data, 'eras', false) as $index => $era) {
            $place = 'eras.'.$index;
            $entry = self::map($era, $place, ['id', 'name', 'start']);
            $start = $entry['start'] ?? null;
            if (null !== $start && !\is_int($start)) {
                throw InvalidCalendar::at($place.'.start', 'is a whole number, or null for an era that counts backward');
            }

            $eras[] = self::build($place, static fn (): Era => new Era(self::string($entry, 'id', $place), self::string($entry, 'name', $place), $start));
        }

        $monthNumbers = [];
        foreach ($months as $index => $month) {
            $monthNumbers[$month->id] = $index + 1;
        }

        $seasons = [];
        foreach (self::list($data, 'seasons', false) as $index => $season) {
            $place = 'seasons.'.$index;
            $entry = self::map($season, $place, ['id', 'name', 'month', 'day']);
            $month = self::string($entry, 'month', $place);
            if (!isset($monthNumbers[$month])) {
                throw InvalidCalendar::at($place.'.month', \sprintf('names no month of the calendar ("%s")', mb_strimwidth($month, 0, 64, '…')));
            }

            $seasons[] = self::build($place, static fn (): Season => new Season(self::string($entry, 'id', $place), self::string($entry, 'name', $place), $monthNumbers[$month], self::int($entry, 'day', $place)));
        }

        $climateZone = $data['climateZone'] ?? null;
        if (null !== $climateZone && !\is_string($climateZone)) {
            throw InvalidCalendar::at('climateZone', 'is a text');
        }

        $leapRule = self::build('leap', static fn (): LeapRule => new LeapRule($cycles));

        return self::build('calendar', static fn (): Calendar => new Calendar(
            self::string($data, 'id', 'calendar'),
            self::string($data, 'name', 'calendar'),
            $months,
            $weekdays,
            self::int($data, 'firstWeekday', 'calendar', 0),
            $leapRule,
            $moons,
            $eras,
            $seasons,
            $climateZone,
        ));
    }

    /**
     * @template T
     *
     * @param Closure(): T $make
     *
     * @return T
     *
     * @throws InvalidCalendar with the place prefixed
     */
    private static function build(string $place, Closure $make): mixed
    {
        try {
            return $make();
        } catch (InvalidCalendar $invalidCalendar) {
            if (str_starts_with($invalidCalendar->getMessage(), $place)) {
                throw $invalidCalendar;
            }

            throw InvalidCalendar::at($place, rtrim($invalidCalendar->getMessage(), '.'));
        }
    }

    /**
     * @param array<array-key, mixed> $entry
     *
     * @throws InvalidCalendar
     */
    private static function moon(array $entry, string $place): Moon
    {
        $id = self::string($entry, 'id', $place);
        $name = self::string($entry, 'name', $place);
        $newMoon = self::int($entry, 'newMoon', $place, 0);
        $cycle = $entry['cycle'] ?? null;
        if (\is_string($cycle)) {
            return Moon::ofDecimalCycle($id, $name, $cycle, $newMoon);
        }

        if (\is_int($cycle)) {
            return new Moon($id, $name, $cycle, 1, $newMoon);
        }

        if (\is_array($cycle) && array_is_list($cycle) && 2 === \count($cycle) && \is_int($cycle[0]) && \is_int($cycle[1])) {
            return new Moon($id, $name, $cycle[0], $cycle[1], $newMoon);
        }

        throw InvalidCalendar::at($place.'.cycle', 'is a decimal number of days as a text, a whole number of days, or [numerator, denominator]');
    }

    /**
     * @param array<array-key, mixed> $data
     *
     * @return list<mixed>
     *
     * @throws InvalidCalendar
     */
    private static function list(array $data, string $key, bool $required): array
    {
        $value = $data[$key] ?? null;
        if (null === $value && !$required) {
            return [];
        }

        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidCalendar::at($key, 'is a list');
        }

        return $value;
    }

    /**
     * @param list<string> $keys
     *
     * @return array<array-key, mixed>
     *
     * @throws InvalidCalendar
     */
    private static function map(mixed $value, string $place, array $keys): array
    {
        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            throw InvalidCalendar::at($place, 'is an object');
        }

        foreach (array_keys($value) as $key) {
            if (!\in_array($key, $keys, true)) {
                throw InvalidCalendar::at($place.'.'.$key, 'is not a key here');
            }
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $entry
     *
     * @throws InvalidCalendar
     */
    private static function string(array $entry, string $key, string $place): string
    {
        $value = $entry[$key] ?? null;
        if (!\is_string($value)) {
            throw InvalidCalendar::at($place.'.'.$key, 'is a text');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $entry
     *
     * @throws InvalidCalendar
     */
    private static function int(array $entry, string $key, string $place, ?int $default = null): int
    {
        $value = $entry[$key] ?? $default;
        if (!\is_int($value)) {
            throw InvalidCalendar::at($place.'.'.$key, 'is a whole number');
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $entry
     *
     * @throws InvalidCalendar
     */
    private static function bool(array $entry, string $key, string $place, bool $default): bool
    {
        $value = $entry[$key] ?? $default;
        if (!\is_bool($value)) {
            throw InvalidCalendar::at($place.'.'.$key, 'is true or false');
        }

        return $value;
    }
}
