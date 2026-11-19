<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Application\Contract;

use Tilttek\AdventCalendarBundle\Application\CalendarEntry;

/**
 * Where the calendars come from: the configuration, the database, or the application's own source. A calendar that is
 * not published is not found.
 */
interface CalendarCatalogue
{
    public function find(string $id): ?CalendarEntry;

    /**
     * The identifiers of the published calendars, for the export of a member's openings.
     *
     * @return list<string>
     */
    public function ids(): array;
}
