<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Unit\Infrastructure;

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
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\BadgesSchemaListener;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\BadgesTables;
use Tilttek\BadgesBundle\Infrastructure\Doctrine\Rows;
use UnexpectedValueException;

#[CoversClass(BadgesTables::class)]
#[CoversClass(BadgesSchemaListener::class)]
#[CoversClass(Rows::class)]
final class TablesTest extends TestCase
{
    public function testTheTables(): void
    {
        $schema = BadgesTables::configure(new Schema());

        $contributions = $schema->getTable(BadgesTables::CONTRIBUTIONS);
        self::assertSame(['badge', 'path', 'activity', 'member', 'quantity', 'occurred_at'], array_map(static fn (Column $column): string => $column->getName(), $contributions->getColumns()));
        self::assertSame([64, 64, 255, 320], [$contributions->getColumn('badge')->getLength(), $contributions->getColumn('path')->getLength(), $contributions->getColumn('activity')->getLength(), $contributions->getColumn('member')->getLength()]);
        self::assertSame(['tilttek_badges_contribution_member', 'tilttek_badges_contribution_activity', 'tilttek_badges_contribution_occurred', 'primary'], array_map(static fn (Index $index): string => $index->getObjectName()->toString(), array_values($contributions->getIndexes())));
        self::assertSame(320, $contributions->getColumn('member')->getLength());
        $key = $contributions->getPrimaryKeyConstraint();
        self::assertNotNull($key);
        self::assertSame(['badge', 'path', 'activity'], array_map(static fn (UnqualifiedName $name): string => $name->toString(), $key->getColumnNames()));

        $awards = $schema->getTable(BadgesTables::AWARDS);
        self::assertSame(['badge', 'member', 'number', 'awarded_at', 'cause', 'manual'], array_map(static fn (Column $column): string => $column->getName(), $awards->getColumns()));
        self::assertFalse($awards->getColumn('cause')->getNotnull());
        self::assertSame([64, 320, 255], [$awards->getColumn('badge')->getLength(), $awards->getColumn('member')->getLength(), $awards->getColumn('cause')->getLength()]);
        self::assertSame(['tilttek_badges_award_awarded', 'primary'], array_map(static fn (Index $index): string => $index->getObjectName()->toString(), array_values($awards->getIndexes())));

        $profiles = $schema->getTable(BadgesTables::PROFILES);
        self::assertSame(['member', 'shown', 'featured', 'title'], array_map(static fn (Column $column): string => $column->getName(), $profiles->getColumns()));
        self::assertFalse($profiles->getColumn('title')->getNotnull());
        self::assertSame(64, $profiles->getColumn('title')->getLength());

        $grants = $schema->getTable(BadgesTables::GRANTS);
        self::assertSame(['member', 'client', 'granted_at'], array_map(static fn (Column $column): string => $column->getName(), $grants->getColumns()));
        self::assertSame(64, $grants->getColumn('client')->getLength());
    }

    public function testTablesAlreadyThereAreLeftAsTheyAre(): void
    {
        $schema = BadgesTables::configure(new Schema());
        self::assertCount(4, BadgesTables::configure($schema)->getTables());
    }

    public function testTheTablesJoinTheSchemaOfTheirConnectionOnly(): void
    {
        $badges = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $other = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $listener = new BadgesSchemaListener($badges);

        $event = new GenerateSchemaEventArgs($this->entityManager($badges), new Schema());
        $listener->postGenerateSchema($event);
        self::assertTrue($event->getSchema()->hasTable(BadgesTables::AWARDS));

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

    private function entityManager(Connection $connection): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([], true);
        $configuration->enableNativeLazyObjects(true);

        return new EntityManager($connection, $configuration);
    }
}
