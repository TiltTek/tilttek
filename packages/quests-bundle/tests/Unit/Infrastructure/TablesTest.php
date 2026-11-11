<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Tests\Unit\Infrastructure;

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
use Doctrine\DBAL\Types\Type;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\QuestsSchemaListener;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\QuestsTables;
use Tilttek\QuestsBundle\Infrastructure\Doctrine\Rows;
use UnexpectedValueException;

#[CoversClass(QuestsTables::class)]
#[CoversClass(QuestsSchemaListener::class)]
#[CoversClass(Rows::class)]
final class TablesTest extends TestCase
{
    public function testTheTables(): void
    {
        $schema = QuestsTables::configure(new Schema());

        $contributions = $schema->getTable(QuestsTables::CONTRIBUTIONS);
        self::assertSame(['quest', 'step', 'activity', 'member', 'value', 'occurred_at'], $this->columns($contributions));
        self::assertSame([64, 64, 255, 320], $this->lengths($contributions, 'quest', 'step', 'activity', 'member'));
        self::assertSame(['quest', 'step', 'activity'], $this->primary($contributions));
        self::assertSame(['tilttek_quests_contribution_quest', 'tilttek_quests_contribution_member', 'tilttek_quests_contribution_activity', 'tilttek_quests_contribution_occurred', 'primary'], $this->indexes($contributions));
        self::assertSame(Types::BIGINT, Type::lookupName($contributions->getColumn('value')->getType()));

        $enrolments = $schema->getTable(QuestsTables::ENROLMENTS);
        self::assertSame(['quest', 'member', 'accepted_at', 'team', 'abandoned_at'], $this->columns($enrolments));
        self::assertSame([64, 320, 320], $this->lengths($enrolments, 'quest', 'member', 'team'));
        self::assertSame([true, true, true, false, false], array_map(static fn (Column $column): bool => $column->getNotnull(), $enrolments->getColumns()));
        self::assertSame(['quest', 'member'], $this->primary($enrolments));
        self::assertSame(['tilttek_quests_enrolment_member', 'primary'], $this->indexes($enrolments));

        $completions = $schema->getTable(QuestsTables::COMPLETIONS);
        self::assertSame(['quest', 'occurrence', 'subject', 'completed_at', 'activity'], $this->columns($completions));
        self::assertSame([64, 200, 320, 255], $this->lengths($completions, 'quest', 'occurrence', 'subject', 'activity'));
        self::assertSame(['quest', 'occurrence', 'subject'], $this->primary($completions));
        self::assertSame(['tilttek_quests_completion_subject', 'tilttek_quests_completion_completed', 'primary'], $this->indexes($completions));

        $duels = $schema->getTable(QuestsTables::DUELS);
        self::assertSame(['duel_key', 'quest', 'challenger', 'opponent', 'offered_at', 'status', 'accepted_at', 'ends_at', 'winner', 'decided_at'], $this->columns($duels));
        self::assertSame([200, 64, 320, 320, 16, 320], $this->lengths($duels, 'duel_key', 'quest', 'challenger', 'opponent', 'status', 'winner'));
        self::assertSame([true, true, true, true, true, true, false, false, false, false], array_map(static fn (Column $column): bool => $column->getNotnull(), $duels->getColumns()));
        self::assertSame(['duel_key'], $this->primary($duels));
        self::assertSame(['tilttek_quests_duel_challenger', 'tilttek_quests_duel_opponent', 'tilttek_quests_duel_status', 'primary'], $this->indexes($duels));

        $definitions = $schema->getTable(QuestsTables::DEFINITIONS);
        self::assertSame(['code', 'definition'], $this->columns($definitions));
        self::assertSame([64], $this->lengths($definitions, 'code'));
        self::assertSame(['code'], $this->primary($definitions));
    }

    public function testTablesAlreadyThereAreLeftAsTheyAre(): void
    {
        $schema = QuestsTables::configure(new Schema());
        self::assertCount(5, QuestsTables::configure($schema)->getTables());
    }

    public function testTheTablesJoinTheSchemaOfTheirConnectionOnly(): void
    {
        $quests = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $other = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $listener = new QuestsSchemaListener($quests);

        $event = new GenerateSchemaEventArgs($this->entityManager($quests), new Schema());
        $listener->postGenerateSchema($event);
        self::assertTrue($event->getSchema()->hasTable(QuestsTables::DUELS));

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
        self::assertSame('2026-11-07T15:00:00+00:00', Rows::optionalDate('2026-11-07 15:00:00')?->format(\DATE_ATOM));
        self::assertNull(Rows::optionalDate(null));
        self::assertEquals(ResourceReference::of('team', 'red'), Rows::optionalReference('team:red'));
        self::assertNull(Rows::optionalReference(null));
        self::assertEquals(ResourceReference::of('member', '1'), Rows::reference('member:1'));
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
