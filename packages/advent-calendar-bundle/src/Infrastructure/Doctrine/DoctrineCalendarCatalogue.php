<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Infrastructure\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psr\Log\LoggerInterface;
use Tilttek\AdventCalendar\Exception\InvalidCalendar;
use Tilttek\AdventCalendarBundle\Application\CalendarEntry;
use Tilttek\AdventCalendarBundle\Application\Contract\CalendarCatalogue;
use Tilttek\AdventCalendarBundle\Domain\Model\CalendarRecord;

/**
 * The calendars the site edits, read through the entity manager: a calendar that is not published, or whose fields no
 * longer hold together, is not found — the second is logged, so that an edit that broke a calendar is seen.
 */
final readonly class DoctrineCalendarCatalogue implements CalendarCatalogue
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function find(string $id): ?CalendarEntry
    {
        $record = $this->entityManager->find(CalendarRecord::class, $id);

        if (!$record instanceof CalendarRecord || !$record->published) {
            return null;
        }

        try {
            return new CalendarEntry($record->toCalendar(), $record->title);
        } catch (InvalidCalendar $invalidCalendar) {
            $this->logger?->error('The advent calendar "{calendar}" does not hold together: {reason}', ['calendar' => $id, 'reason' => $invalidCalendar->getMessage()]);

            return null;
        }
    }

    #[Override]
    public function ids(): array
    {
        /** @var list<string> */
        return array_column($this->entityManager->createQueryBuilder()
            ->select('calendar.id')
            ->from(CalendarRecord::class, 'calendar')
            ->where('calendar.published = true')
            ->orderBy('calendar.id', 'ASC')
            ->getQuery()
            ->getScalarResult(), 'id');
    }
}
