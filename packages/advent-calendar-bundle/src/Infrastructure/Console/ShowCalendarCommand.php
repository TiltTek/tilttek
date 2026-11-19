<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Infrastructure\Console;

use Psr\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\AdventCalendar\BoxView;
use Tilttek\AdventCalendarBundle\Application\CalendarEntry;
use Tilttek\AdventCalendarBundle\Application\Contract\CalendarCatalogue;
use Tilttek\AdventCalendarBundle\Application\VisitorBoxes;

/**
 * Shows the boxes of a published calendar for the people who run the site: each box's date, the first and the last day
 * it may be opened, its state now for a member who has not opened it, and its surprise. No member's openings are read.
 */
#[AsCommand(name: self::NAME, description: self::DESCRIPTION)]
final readonly class ShowCalendarCommand
{
    public const string NAME = 'tilttek:advent-calendar:show';

    public const string DESCRIPTION = 'Shows the boxes of an advent calendar, their windows and their surprises';

    public function __construct(
        private CalendarCatalogue $calendars,
        private ClockInterface $clock,
    ) {
    }

    public function __invoke(SymfonyStyle $io, #[Argument('The identifier of the calendar')] string $calendar): int
    {
        $entry = $this->calendars->find($calendar);

        if (!$entry instanceof CalendarEntry) {
            $io->error(\sprintf('No published calendar "%s".', $calendar));

            return Command::FAILURE;
        }

        $zone = $entry->calendar->timezone;
        $io->title($entry->title);
        $io->text(\sprintf('%s, %s; closes at the end of %s.', $zone->getName(), Policies::describe($entry->calendar->policy), $entry->calendar->closingDate));
        $io->table(
            ['Box', 'Date', 'First day', 'Last day', 'Now', 'Surprise'],
            array_map(static fn (BoxView $box): array => [
                $box->day,
                $box->date,
                $box->window->from->setTimezone($zone)->format('Y-m-d'),
                $box->window->until->setTimezone($zone)->modify('-1 day')->format('Y-m-d'),
                $box->state->value,
                \sprintf('%s %s × %d', $entry->calendar->surprise($box->day)->kind->value, $entry->calendar->surprise($box->day)->reference, $entry->calendar->surprise($box->day)->quantity),
            ], VisitorBoxes::of($entry->calendar, $this->clock->now())),
        );

        return Command::SUCCESS;
    }
}
