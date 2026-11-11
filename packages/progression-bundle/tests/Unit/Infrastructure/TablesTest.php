<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\OraclePlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\ProgressionSchemaListener;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\ProgressionTables;
use Tilttek\ProgressionBundle\Infrastructure\Doctrine\Rows;
use UnexpectedValueException;

#[CoversClass(ProgressionTables::class)]
#[CoversClass(ProgressionSchemaListener::class)]
#[CoversClass(Rows::class)]
final class TablesTest extends TestCase
{
    public function testTheTables(): void
    {
        $schema = ProgressionTables::configure(new Schema());

        $gains = $schema->getTable(ProgressionTables::GAINS);
        self::assertSame(['track', 'rule_name', 'activity', 'subject', 'member', 'amount', 'occurred_at', 'season'], $this->columns($gains));
        self::assertSame([64, 64, 255, 320, 320, 64], [$gains->getColumn('track')->getLength(), $gains->getColumn('rule_name')->getLength(), $gains->getColumn('activity')->getLength(), $gains->getColumn('subject')->getLength(), $gains->getColumn('member')->getLength(), $gains->getColumn('season')->getLength()]);
        self::assertSame(['tilttek_progression_gain_subject', 'tilttek_progression_gain_member', 'tilttek_progression_gain_activity', 'tilttek_progression_gain_occurred', 'primary'], $this->indexes($gains));
        self::assertSame(['track', 'rule_name', 'activity'], $this->primary($gains));

        $milestones = $schema->getTable(ProgressionTables::MILESTONES);
        self::assertSame(['track', 'milestone', 'subject', 'season', 'member', 'reached_at', 'activity'], $this->columns($milestones));
        self::assertSame(['subject', 'track', 'milestone', 'season'], $this->primary($milestones));
        self::assertSame([64, 64, 320, 64, 320, 255], $this->lengths($milestones, 'track', 'milestone', 'subject', 'season', 'member', 'activity'));
        self::assertSame(['tilttek_progression_milestone_member', 'primary'], $this->indexes($milestones));

        $gestures = $schema->getTable(ProgressionTables::GESTURES);
        self::assertSame(['streak', 'activity', 'member', 'unit', 'occurred_at'], $this->columns($gestures));
        self::assertSame(['streak', 'activity'], $this->primary($gestures));
        self::assertSame([64, 255, 320], $this->lengths($gestures, 'streak', 'activity', 'member'));
        self::assertSame(['tilttek_progression_streak_gesture_member', 'tilttek_progression_streak_gesture_activity', 'tilttek_progression_streak_gesture_occurred', 'primary'], $this->indexes($gestures));

        $freezes = $schema->getTable(ProgressionTables::FREEZES);
        self::assertSame(['streak', 'member', 'unit'], $this->columns($freezes));
        self::assertSame(['member', 'streak', 'unit'], $this->primary($freezes));
        self::assertSame([64, 320], $this->lengths($freezes, 'streak', 'member'));

        $states = $schema->getTable(ProgressionTables::STATES);
        self::assertSame(['streak', 'member', 'current_run', 'best_run', 'run_start', 'last_active', 'timezone'], $this->columns($states));
        self::assertFalse($states->getColumn('run_start')->getNotnull());
        self::assertTrue($states->getColumn('last_active')->getNotnull());
        self::assertSame(['member', 'streak'], $this->primary($states));
        self::assertSame([64, 320, 64], $this->lengths($states, 'streak', 'member', 'timezone'));
        self::assertSame(['tilttek_progression_streak_state_alive', 'primary'], $this->indexes($states));
    }

    public function testTablesAlreadyThereAreLeftAsTheyAre(): void
    {
        $schema = ProgressionTables::configure(new Schema());
        self::assertCount(5, ProgressionTables::configure($schema)->getTables());
    }

    public function testTheTablesJoinTheSchemaOfTheirConnectionOnly(): void
    {
        $badges = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $other = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $listener = new ProgressionSchemaListener($badges);

        $event = new GenerateSchemaEventArgs($this->entityManager($badges), new Schema());
        $listener->postGenerateSchema($event);
        self::assertTrue($event->getSchema()->hasTable(ProgressionTables::GAINS));

        $elsewhere = new GenerateSchemaEventArgs($this->entityManager($other), new Schema());
        $listener->postGenerateSchema($elsewhere);
        self::assertSame([], $elsewhere->getSchema()->getTables());
    }

    public function testTheInsertionThatIgnoresARowAlreadyThere(): void
    {
        self::assertSame('INSERT INTO t (a) VALUES (?) ON CONFLICT (a) DO NOTHING', Rows::ignoring(new PostgreSQLPlatform(), 'INTO t (a) VALUES (?)', 'a'));
        self::assertSame('INSERT IGNORE INTO t (a) VALUES (?)', Rows::ignoring(new MySQLPlatform(), 'INTO t (a) VALUES (?)', 'a'));
        self::assertSame('INSERT IGNORE INTO t (a) VALUES (?)', Rows::ignoring(new MariaDBPlatform(), 'INTO t (a) VALUES (?)', 'a'));
        self::assertSame('INSERT OR IGNORE INTO t (a) VALUES (?)', Rows::ignoring(new SQLitePlatform(), 'INTO t (a) VALUES (?)', 'a'));
        self::assertSame('INSERT INTO t (a) VALUES (?)', Rows::ignoring(new OraclePlatform(), 'INTO t (a) VALUES (?)', 'a'));
    }

    public function testTheValuesOfARow(): void
    {
        self::assertSame(42, Rows::integer(42));
        self::assertSame(42, Rows::integer('42'));
        self::assertSame(-7, Rows::integer('-7'));
        self::assertSame('a', Rows::text('a'));
        self::assertSame('2026-11-07T15:00:00+00:00', Rows::utc(new DateTimeImmutable('2026-11-07 10:00:00 America/Toronto'))->format(\DATE_ATOM));
        self::assertSame('2026-11-07T15:00:00+00:00', Rows::date('2026-11-07 15:00:00')->format(\DATE_ATOM));
    }

    public function testAnIntegerColumn(): void
    {
        $this->expectExceptionObject(new UnexpectedValueException('An integer column holds string.'));
        Rows::integer('4 2');
    }

    public function testAnIntegerColumnIsWhole(): void
    {
        $this->expectExceptionObject(new UnexpectedValueException('An integer column holds string.'));
        Rows::integer("42\n");
    }

    public function testAnIntegerColumnOfAnotherType(): void
    {
        $this->expectExceptionObject(new UnexpectedValueException('An integer column holds float.'));
        Rows::integer(4.2);
    }

    public function testATextColumn(): void
    {
        $this->expectExceptionObject(new UnexpectedValueException('A text column holds stdClass.'));
        Rows::text(new stdClass());
    }

    /**
     * @return list<?int>
     */
    private function lengths(Table $table, string ...$columns): array
    {
        return array_map(static fn (string $column): ?int => $table->getColumn($column)->getLength(), array_values($columns));
    }

    /**
     * @return list<string>
     */
    private function columns(Table $table): array
    {
        return array_map(static fn (Column $column): string => $column->getName(), $table->getColumns());
    }

    /**
     * @return list<string>
     */
    private function indexes(Table $table): array
    {
        return array_values(array_map(static fn (Index $index): string => $index->getObjectName()->toString(), $table->getIndexes()));
    }

    /**
     * @return list<string>
     */
    private function primary(Table $table): array
    {
        $key = $table->getPrimaryKeyConstraint();
        self::assertNotNull($key);

        return array_map(static fn (UnqualifiedName $name): string => $name->toString(), $key->getColumnNames());
    }

    private function entityManager(Connection $connection): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([], true);
        $configuration->enableNativeLazyObjects(true);

        return new EntityManager($connection, $configuration);
    }
}
