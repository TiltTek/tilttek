<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Application;

use Override;
use Tilttek\AdventCalendarBundle\Application\Contract\CalendarCatalogue;

/**
 * The calendars of several catalogues, asked in order: those of the configuration first, then those of the database.
 * A calendar of an earlier catalogue hides one of the same identifier in a later one.
 */
final readonly class Calendars implements CalendarCatalogue
{
    /**
     * @param iterable<CalendarCatalogue> $catalogues
     */
    public function __construct(
        private iterable $catalogues,
    ) {
    }

    #[Override]
    public function find(string $id): ?CalendarEntry
    {
        foreach ($this->catalogues as $catalogue) {
            $entry = $catalogue->find($id);

            if ($entry instanceof CalendarEntry) {
                return $entry;
            }
        }

        return null;
    }

    #[Override]
    public function ids(): array
    {
        $ids = [];

        foreach ($this->catalogues as $catalogue) {
            $ids = [...$ids, ...$catalogue->ids()];
        }

        return array_values(array_unique($ids));
    }
}
