<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\GameEngineSchemaListener;
use Tilttek\GameEngineBundle\Infrastructure\Doctrine\GameEngineTables;

#[CoversClass(GameEngineSchemaListener::class)]
#[CoversClass(GameEngineTables::class)]
final class SchemaTest extends TestCase
{
    public function testTheTablesJoinTheSchemaOfTheirConnectionOnly(): void
    {
        $logs = $this->sqlite();
        $other = $this->sqlite();

        $event = new GenerateSchemaEventArgs($this->entityManager($logs), $this->empty());
        new GameEngineSchemaListener($logs)->postGenerateSchema($event);
        self::assertSame([GameEngineTables::MOVES, GameEngineTables::TABLES], $this->names($event->getSchema()));

        $elsewhere = new GenerateSchemaEventArgs($this->entityManager($other), $this->empty());
        new GameEngineSchemaListener($logs)->postGenerateSchema($elsewhere);
        self::assertSame([], $elsewhere->getSchema()->getTables());
    }

    public function testATableTheSchemaHasIsLeftAsItIs(): void
    {
        $schema = GameEngineTables::configure($this->empty());
        $again = GameEngineTables::configure($schema);

        self::assertCount(2, $again->getTables());
        self::assertSame($schema->getTable(GameEngineTables::MOVES)->getColumns(), $again->getTable(GameEngineTables::MOVES)->getColumns());
        self::assertFalse($again->getTable(GameEngineTables::MOVES)->getColumn('seat')->getNotnull());
        self::assertTrue($again->getTable(GameEngineTables::MOVES)->getColumn('type')->getNotnull());
    }

    public function testTheTablesAsEachDatabaseCreatesThem(): void
    {
        $tables = GameEngineTables::configure($this->empty())->getTables();

        self::assertSame([
            'CREATE TABLE tilttek_game_engine_table (game_id VARCHAR(128) NOT NULL, game VARCHAR(64) NOT NULL, version VARCHAR(64) NOT NULL, seed VARCHAR(64) NOT NULL, seats TEXT NOT NULL, options TEXT NOT NULL, moves INT NOT NULL, revision BIGINT NOT NULL, PRIMARY KEY (game_id))',
            'CREATE TABLE tilttek_game_engine_move (game_id VARCHAR(128) NOT NULL, number INT NOT NULL, type VARCHAR(64) NOT NULL, seat VARCHAR(128) DEFAULT NULL, payload TEXT NOT NULL, drew BOOLEAN NOT NULL, PRIMARY KEY (game_id, number))',
        ], new PostgreSQLPlatform()->getCreateTablesSQL($tables));
        self::assertSame([
            'CREATE TABLE tilttek_game_engine_table (game_id VARCHAR(128) NOT NULL, game VARCHAR(64) NOT NULL, version VARCHAR(64) NOT NULL, seed VARCHAR(64) NOT NULL, seats LONGTEXT NOT NULL, options LONGTEXT NOT NULL, moves INT NOT NULL, revision BIGINT NOT NULL, PRIMARY KEY (game_id))',
            'CREATE TABLE tilttek_game_engine_move (game_id VARCHAR(128) NOT NULL, number INT NOT NULL, type VARCHAR(64) NOT NULL, seat VARCHAR(128) DEFAULT NULL, payload LONGTEXT NOT NULL, drew TINYINT NOT NULL, PRIMARY KEY (game_id, number))',
        ], array_map(static fn (string $sql): string => preg_replace('/ DEFAULT CHARACTER SET .*$/', '', $sql) ?? $sql, new MySQLPlatform()->getCreateTablesSQL($tables)));
    }

    private function sqlite(): Connection
    {
        return DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
    }

    /**
     * An empty schema, as a database without tables gives it.
     */
    private function empty(): Schema
    {
        return $this->sqlite()->createSchemaManager()->introspectSchema();
    }

    /**
     * @return list<string>
     */
    private function names(Schema $schema): array
    {
        $names = array_map(static fn (Table $table): string => $table->getObjectName()->toString(), $schema->getTables());
        sort($names);

        return $names;
    }

    private function entityManager(Connection $connection): EntityManager
    {
        $configuration = ORMSetup::createAttributeMetadataConfig([], true);
        $configuration->enableNativeLazyObjects(true);

        return new EntityManager($connection, $configuration);
    }
}
