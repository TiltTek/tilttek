<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Infrastructure\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\AdventCalendarBundle\Application\CalendarEntry;
use Tilttek\AdventCalendarBundle\Application\Contract\CalendarCatalogue;

/**
 * Lists the published calendars, those of the configuration and those of the database: their identifier, title,
 * timezone, number of boxes, first and closing dates, and opening policy.
 */
#[AsCommand(name: self::NAME, description: self::DESCRIPTION)]
final readonly class ListCalendarsCommand
{
    public const string NAME = 'tilttek:advent-calendar:list';

    public const string DESCRIPTION = 'Lists the published advent calendars';

    public function __construct(
        private CalendarCatalogue $calendars,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $entries = array_filter(array_map($this->calendars->find(...), $this->calendars->ids()));

        if ([] === $entries) {
            $io->note('No calendar is published.');

            return Command::SUCCESS;
        }

        $io->table(
            ['Calendar', 'Title', 'Timezone', 'Boxes', 'First date', 'Closing date', 'Policy'],
            array_map(static fn (CalendarEntry $entry): array => [
                $entry->calendar->id,
                $entry->title,
                $entry->calendar->timezone->getName(),
                $entry->calendar->days(),
                $entry->calendar->firstDate,
                $entry->calendar->closingDate,
                Policies::describe($entry->calendar->policy),
            ], $entries),
        );

        return Command::SUCCESS;
    }
}
