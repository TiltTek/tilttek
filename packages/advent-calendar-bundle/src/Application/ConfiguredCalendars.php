<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Application;

use Override;
use Tilttek\AdventCalendar\Calendar;
use Tilttek\AdventCalendar\PolicyKind;
use Tilttek\AdventCalendar\Surprise;
use Tilttek\AdventCalendar\SurpriseKind;
use Tilttek\AdventCalendarBundle\Application\Contract\CalendarCatalogue;
use Tilttek\AdventCalendarBundle\Domain\Model\CalendarRecord;

/**
 * The calendars of the configuration, `tilttek_advent_calendar.calendars`, built once when the container is: each is
 * checked by the library as it is read, so that a calendar that does not hold together stops the application at its
 * first request rather than on the day of a box.
 *
 * @phpstan-type CalendarConfig array{title: string, timezone: string, first_date: string, closing_date: ?string, policy: string, catch_up_days: int, published: bool, surprises: list<array{kind: string, reference: string, quantity: int}>}
 */
final readonly class ConfiguredCalendars implements CalendarCatalogue
{
    /** @var array<string, CalendarEntry> */
    private array $entries;

    /**
     * @param array<string, CalendarConfig> $calendars by identifier
     */
    public function __construct(array $calendars = [])
    {
        $entries = [];

        foreach ($calendars as $id => $calendar) {
            if ($calendar['published']) {
                $entries[$id] = new CalendarEntry(self::calendar($id, $calendar), $calendar['title']);
            }
        }

        $this->entries = $entries;
    }

    /**
     * @param CalendarConfig $calendar
     */
    public static function calendar(string $id, array $calendar): Calendar
    {
        return Calendar::of(
            $id,
            $calendar['timezone'],
            $calendar['first_date'],
            array_map(static fn (array $surprise): Surprise => Surprise::of(SurpriseKind::from($surprise['kind']), $surprise['reference'], $surprise['quantity']), $calendar['surprises']),
            CalendarRecord::policyOf(PolicyKind::from($calendar['policy']), $calendar['catch_up_days']),
            $calendar['closing_date'],
        );
    }

    #[Override]
    public function find(string $id): ?CalendarEntry
    {
        return $this->entries[$id] ?? null;
    }

    #[Override]
    public function ids(): array
    {
        return array_keys($this->entries);
    }
}
