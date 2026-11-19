<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Infrastructure\Console;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Tilttek\AdventCalendar\Exception\InvalidCalendar;
use Tilttek\AdventCalendarBundle\Domain\Model\CalendarRecord;

/**
 * Checks every calendar of the database, published or not, as the library reads it: a calendar whose fields no longer
 * hold together is not shown to the members, and this command names it with the reason, and fails, so that a deploy or
 * a scheduled task sees it before a member does.
 */
#[AsCommand(name: self::NAME, description: self::DESCRIPTION)]
final readonly class CheckCalendarsCommand
{
    public const string NAME = 'tilttek:advent-calendar:check';

    public const string DESCRIPTION = 'Checks that every advent calendar of the database holds together';

    public function __construct(
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function __invoke(SymfonyStyle $io): int
    {
        $records = $this->entityManager->getRepository(CalendarRecord::class)->findBy([], ['id' => 'ASC']);
        $broken = [];

        foreach ($records as $record) {
            try {
                $record->toCalendar();
            } catch (InvalidCalendar $invalidCalendar) {
                $broken[] = [$record->id, $record->published ? 'yes' : 'no', $invalidCalendar->getMessage()];
            }
        }

        if ([] === $broken) {
            $io->success(\sprintf('%d calendars of the database hold together.', \count($records)));

            return Command::SUCCESS;
        }

        $io->table(['Calendar', 'Published', 'Reason'], $broken);
        $io->error(\sprintf('%d of %d calendars of the database do not hold together.', \count($broken), \count($records)));

        return Command::FAILURE;
    }
}
