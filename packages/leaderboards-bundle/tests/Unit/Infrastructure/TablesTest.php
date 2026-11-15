<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure;

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
use Doctrine\DBAL\Schema\Index\IndexedColumn;
use Doctrine\DBAL\Schema\Name\UnqualifiedName;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Tilttek\Leaderboards\Contribution;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalScoreStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\LeaderboardsSchemaListener;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\LeaderboardsTables;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\Rows;
use UnexpectedValueException;

#[CoversClass(LeaderboardsTables::class)]
#[CoversClass(LeaderboardsSchemaListener::class)]
#[CoversClass(Rows::class)]
#[CoversClass(DbalScoreStore::class)]
final class TablesTest extends TestCase
{
    public function testTheTables(): void
    {
        $schema = LeaderboardsTables::configure(Schema::editor()->create());

        $contributions = $schema->getTable(LeaderboardsTables::CONTRIBUTIONS);
        self::assertSame(['board', 'activity', 'period', 'member', 'amount', 'occurred_at', 'tie_breaks', 'tie_break_1', 'tie_break_2'], $this->columns($contributions));
        self::assertSame([96, 255, 64, 320], $this->lengths($contributions, 'board', 'activity', 'period', 'member'));
        self::assertSame(['board', 'activity'], $this->primary($contributions));
        self::assertSame(['tilttek_leaderboards_contribution_score', 'tilttek_leaderboards_contribution_activity', 'tilttek_leaderboards_contribution_occurred', 'tilttek_leaderboards_contribution_member', 'primary'], $this->indexes($contributions));

        $scores = $schema->getTable(LeaderboardsTables::SCORES);
        self::assertSame(['board', 'period', 'member', 'score', 'reached_at', 'tie_breaks', 'tie_break_1', 'tie_break_2'], $this->columns($scores));
        self::assertSame([96, 64, 320], $this->lengths($scores, 'board', 'period', 'member'));
        self::assertSame(['board', 'period', 'member'], $this->primary($scores));
        self::assertSame(['tilttek_leaderboards_score_rank', 'tilttek_leaderboards_score_member', 'primary'], $this->indexes($scores));
        self::assertSame(['board', 'period', 'score', 'tie_break_1', 'tie_break_2', 'reached_at'], array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $scores->getIndex('tilttek_leaderboards_score_rank')->getIndexedColumns()));

        $snapshots = $schema->getTable(LeaderboardsTables::SNAPSHOTS);
        self::assertSame(['board', 'period', 'taken_at', 'participants'], $this->columns($snapshots));
        self::assertSame([96, 64], $this->lengths($snapshots, 'board', 'period'));
        self::assertSame(['board', 'period'], $this->primary($snapshots));

        $placings = $schema->getTable(LeaderboardsTables::PLACINGS);
        self::assertSame(['board', 'period', 'member', 'placing', 'score', 'reached_at'], $this->columns($placings));
        self::assertSame([96, 64, 320], $this->lengths($placings, 'board', 'period', 'member'));
        self::assertSame(['board', 'period', 'member'], $this->primary($placings));
        self::assertSame(['tilttek_leaderboards_placing_member', 'primary'], $this->indexes($placings));

        $appearances = $schema->getTable(LeaderboardsTables::APPEARANCES);
        self::assertSame(['member', 'visibility', 'pseudonym'], $this->columns($appearances));
        self::assertSame([320, 16, 160], $this->lengths($appearances, 'member', 'visibility', 'pseudonym'));
        self::assertFalse($appearances->getColumn('pseudonym')->getNotnull());
        self::assertTrue($appearances->getColumn('visibility')->getNotnull());
        self::assertSame(['member'], $this->primary($appearances));

        $tiers = $schema->getTable(LeaderboardsTables::LEAGUE_TIERS);
        self::assertSame(['board', 'member', 'tier'], $this->columns($tiers));
        self::assertSame([64, 320, 64], $this->lengths($tiers, 'board', 'member', 'tier'));
        self::assertSame(['board', 'member'], $this->primary($tiers));
        self::assertSame(['tilttek_leaderboards_league_tier_member', 'primary'], $this->indexes($tiers));

        $memberships = $schema->getTable(LeaderboardsTables::LEAGUE_MEMBERSHIPS);
        self::assertSame(['board', 'week', 'member', 'tier', 'division', 'cohort', 'joined_at', 'movement'], $this->columns($memberships));
        self::assertSame([64, 64, 320, 64, 16], $this->lengths($memberships, 'board', 'week', 'member', 'tier', 'movement'));
        self::assertFalse($memberships->getColumn('movement')->getNotnull());
        self::assertTrue($memberships->getColumn('tier')->getNotnull());
        self::assertSame(['board', 'week', 'member'], $this->primary($memberships));
        self::assertSame(['tilttek_leaderboards_league_membership_division', 'tilttek_leaderboards_league_membership_member', 'primary'], $this->indexes($memberships));
        self::assertSame(['board', 'week', 'tier', 'division'], array_map(static fn (IndexedColumn $column): string => $column->getColumnName()->toString(), $memberships->getIndex('tilttek_leaderboards_league_membership_division')->getIndexedColumns()));

        $weeks = $schema->getTable(LeaderboardsTables::LEAGUE_WEEKS);
        self::assertSame(['board', 'week', 'settled_at'], $this->columns($weeks));
        self::assertSame([64, 64], $this->lengths($weeks, 'board', 'week'));
        self::assertSame(['board', 'week'], $this->primary($weeks));

        $leads = $schema->getTable(LeaderboardsTables::LEADS);
        self::assertSame(['board', 'period', 'member', 'taken_at'], $this->columns($leads));
        self::assertSame([96, 64, 320], $this->lengths($leads, 'board', 'period', 'member'));
        self::assertSame(['board', 'period', 'member'], $this->primary($leads));
        self::assertSame(['tilttek_leaderboards_lead_member', 'primary'], $this->indexes($leads));

        foreach ([[$contributions, 'occurred_at'], [$scores, 'reached_at'], [$snapshots, 'taken_at'], [$placings, 'reached_at'], [$memberships, 'joined_at'], [$weeks, 'settled_at']] as [$table, $column]) {
            self::assertSame(Types::DATETIME_UTC_IMMUTABLE, $table->getColumn($column)->getTypeName());
        }
    }

    public function testAMomentOfAnotherZoneIsKeptInUtc(): void
    {
        $connection = Database::connection();
        new DbalScoreStore($connection)->add(new Contribution('games', 'ever', ResourceReference::fromString('member:1'), 'k1', 1, new DateTimeImmutable('2026-11-07 10:00:00 America/Toronto')));

        self::assertSame('2026-11-07 15:00:00', $connection->fetchOne('SELECT occurred_at FROM '.LeaderboardsTables::CONTRIBUTIONS));
        self::assertSame('2026-11-07T15:00:00+00:00', new DbalScoreStore($connection)->contributions('games', 'ever', ResourceReference::fromString('member:1'))[0]->occurredAt->format(\DATE_ATOM));
    }

    public function testTablesAlreadyThereAreLeftAsTheyAre(): void
    {
        $schema = LeaderboardsTables::configure(Schema::editor()->create());
        self::assertCount(9, LeaderboardsTables::configure($schema)->getTables());
    }

    public function testTheTablesJoinTheSchemaOfTheirConnectionOnly(): void
    {
        $badges = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $other = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $listener = new LeaderboardsSchemaListener($badges);

        $event = new GenerateSchemaEventArgs($this->entityManager($badges), Schema::editor()->create());
        $listener->postGenerateSchema($event);
        self::assertTrue($event->getSchema()->hasTable(LeaderboardsTables::SCORES));

        $elsewhere = new GenerateSchemaEventArgs($this->entityManager($other), Schema::editor()->create());
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
        return array_map(static fn (Column $column): string => $column->getObjectName()->toString(), $table->getColumns());
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
