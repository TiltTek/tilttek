<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Tilttek\AdventCalendar\AdventCalendar;
use Tilttek\AdventCalendar\Contract\OpeningStore;
use Tilttek\AdventCalendar\Event\BoxOpened;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberOpenings;
use Tilttek\AdventCalendarBundle\Infrastructure\Doctrine\AdventCalendarTables;
use Tilttek\AdventCalendarBundle\Infrastructure\Doctrine\DbalOpeningStore;
use Tilttek\AdventCalendarBundle\Tests\Unit\Fixture\Calendars;
use UnexpectedValueException;

/**
 * The openings on SQLite, their table created from the schema the bundle adds.
 */
#[CoversClass(DbalOpeningStore::class)]
#[CoversClass(AdventCalendarTables::class)]
final class DbalOpeningStoreTest extends OpeningStoreTestCase
{
    private ?Connection $connection = null;

    public function testTheTable(): void
    {
        $this->store();
        $table = $this->connection?->createSchemaManager()->introspectTableByUnquotedName(AdventCalendarTables::OPENINGS);

        self::assertNotNull($table);
        self::assertSame(['"calendar"', '"member"', '"day"', '"opened_at"', '"delivered"'], array_map(static fn (Column $column): string => $column->getObjectName()->toString(), $table->getColumns()));
        self::assertSame(64, $table->getColumn('calendar')->getLength());
        self::assertSame(320, $table->getColumn('member')->getLength());
        self::assertSame(['"calendar"', '"member"', '"day"'], array_map(static fn (UnqualifiedName $column): string => $column->toString(), $table->getPrimaryKeyConstraint()?->getColumnNames() ?? []));
        self::assertTrue($table->hasIndex('tilttek_advent_calendar_opening_member'));
    }

    public function testASchemaThatHasTheTableIsLeftAsItIs(): void
    {
        $schema = AdventCalendarTables::configure($this->emptySchema());

        self::assertSame($schema, AdventCalendarTables::configure($schema));
    }

    public function testARefusedInsertLeavesTheCallersTransactionUsable(): void
    {
        $store = $this->store();
        $ann = ResourceReference::of('member', '44');
        $this->assertInstanceOf(Connection::class, $this->connection);

        $this->connection->beginTransaction();
        self::assertTrue($store->add(self::opening('advent', $ann, 1, '2026-12-01 10:00:00')));
        self::assertFalse($store->add(self::opening('advent', $ann, 1, '2026-12-01 11:00:00')));
        self::assertTrue($store->add(self::opening('advent', $ann, 2, '2026-12-02 10:00:00')));
        $this->connection->commit();

        self::assertCount(2, $store->openingsOf('advent', $ann));
    }

    public function testTwoRequestsThatOpenOneBoxGrantItOnce(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'advent');
        \assert(\is_string($path));
        $first = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
        $second = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'path' => $path]);
        $first->createSchemaManager()->createTable(AdventCalendarTables::configure($this->emptySchema())->getTable(AdventCalendarTables::OPENINGS));
        $granted = 0;
        $dispatcher = new EventDispatcher();
        $dispatcher->addListener(BoxOpened::class, static function () use (&$granted): void {
            ++$granted;
        });
        $clock = new MockClock('2026-12-03 10:00:00 America/Toronto');
        $ann = ResourceReference::of('member', '44');

        try {
            $one = new AdventCalendar(new DbalOpeningStore($first), $clock, $dispatcher)->open(Calendars::advent(), $ann, 3);
            $other = new AdventCalendar(new DbalOpeningStore($second), $clock, $dispatcher)->open(Calendars::advent(), $ann, 3);
        } finally {
            unlink($path);
        }

        self::assertTrue($one->first);
        self::assertFalse($other->first);
        self::assertSame(1, $granted);
    }

    public function testARowThatIsNotTextIsReported(): void
    {
        $store = $this->store();
        $this->assertInstanceOf(Connection::class, $this->connection);
        $this->connection->executeStatement(\sprintf("INSERT INTO %s (calendar, member, day, opened_at) VALUES ('advent', 'member:44', 1, 20261201)", AdventCalendarTables::OPENINGS));

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessageIsOrContains('A text column of the openings holds int.');

        $store->openingsOf('advent', ResourceReference::of('member', '44'));
    }

    private function emptySchema(): Schema
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true])->createSchemaManager()->introspectSchema();
    }

    #[Override]
    protected function store(): OpeningStore&MemberOpenings
    {
        $this->connection = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $this->connection->createSchemaManager()->createTable(AdventCalendarTables::configure($this->emptySchema())->getTable(AdventCalendarTables::OPENINGS));

        return new DbalOpeningStore($this->connection);
    }
}
