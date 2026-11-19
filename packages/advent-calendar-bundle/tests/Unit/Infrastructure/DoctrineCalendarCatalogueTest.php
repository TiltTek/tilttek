<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\ORM\Tools\ToolEvents;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use Stringable;
use Tilttek\AdventCalendarBundle\Application\CalendarEntry;
use Tilttek\AdventCalendarBundle\Domain\Model\CalendarRecord;
use Tilttek\AdventCalendarBundle\Infrastructure\Doctrine\AdventCalendarTables;
use Tilttek\AdventCalendarBundle\Infrastructure\Doctrine\DoctrineCalendarCatalogue;
use Tilttek\AdventCalendarBundle\Infrastructure\Doctrine\OpeningsSchemaListener;
use Tilttek\AdventCalendarBundle\Tests\Unit\Fixture\Calendars;

/**
 * The calendars the site edits on SQLite, their table created from the mapping, with the table of the openings the
 * schema listener adds; each calendar read back with the entity manager cleared.
 */
#[CoversClass(DoctrineCalendarCatalogue::class)]
#[CoversClass(OpeningsSchemaListener::class)]
final class DoctrineCalendarCatalogueTest extends TestCase
{
    public function testAPublishedCalendarIsReadBackAndADraftIsNot(): void
    {
        $entityManager = $this->entityManager();
        $entityManager->persist(CalendarRecord::fromCalendar(Calendars::advent(), 'Advent 2026'));
        $entityManager->persist(CalendarRecord::fromCalendar(Calendars::advent(id: 'draft'), 'Draft', false));
        $entityManager->persist(CalendarRecord::fromCalendar(Calendars::advent(id: 'a-first'), 'First'));
        $entityManager->flush();
        $entityManager->clear();

        $catalogue = new DoctrineCalendarCatalogue($entityManager);

        $entry = $catalogue->find('advent-2026');
        self::assertInstanceOf(CalendarEntry::class, $entry);
        self::assertSame('Advent 2026', $entry->title);
        self::assertEquals(Calendars::advent(), $entry->calendar);
        self::assertNull($catalogue->find('draft'));
        self::assertNull($catalogue->find('summer'));
        self::assertSame(['a-first', 'advent-2026'], $catalogue->ids());
    }

    public function testACalendarThatNoLongerHoldsTogetherIsLoggedAndNotFound(): void
    {
        $entityManager = $this->entityManager();
        $record = CalendarRecord::fromCalendar(Calendars::advent(), 'Advent 2026');
        $record->timezone = 'Mars/Olympus';

        $entityManager->persist($record);
        $entityManager->flush();

        $logger = new class extends AbstractLogger {
            /** @var list<array{string, string, array<array-key, mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [\is_scalar($level) ? (string) $level : '', (string) $message, $context];
            }
        };

        self::assertNull(new DoctrineCalendarCatalogue($entityManager, $logger)->find('advent-2026'));
        self::assertSame([['error', 'The advent calendar "{calendar}" does not hold together: {reason}', ['calendar' => 'advent-2026', 'reason' => '"Mars/Olympus" is not a timezone.']]], $logger->records);
        self::assertNull(new DoctrineCalendarCatalogue($entityManager)->find('advent-2026'));
    }

    public function testTheSchemaOfTheEntityManagerHoldsTheOpeningsAndThatOfAnotherDoesNot(): void
    {
        $entityManager = $this->entityManager();
        $other = $this->entityManager();

        self::assertTrue($entityManager->getConnection()->createSchemaManager()->tablesExist([AdventCalendarTables::OPENINGS, CalendarRecord::TABLE]));

        $empty = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])->createSchemaManager()->introspectSchema();
        $event = new GenerateSchemaEventArgs($other, $empty);
        new OpeningsSchemaListener($entityManager->getConnection())->postGenerateSchema($event);

        self::assertFalse($event->getSchema()->hasTable(AdventCalendarTables::OPENINGS));

        new OpeningsSchemaListener($other->getConnection())->postGenerateSchema($event);

        self::assertTrue($event->getSchema()->hasTable(AdventCalendarTables::OPENINGS));
    }

    private function entityManager(): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 3).'/src/Domain/Model'], true);
        $configuration->enableNativeLazyObjects(true);

        $entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]), $configuration);
        $entityManager->getEventManager()->addEventListener(ToolEvents::postGenerateSchema, new OpeningsSchemaListener($entityManager->getConnection()));
        new SchemaTool($entityManager)->createSchema($entityManager->getMetadataFactory()->getAllMetadata());

        return $entityManager;
    }
}
