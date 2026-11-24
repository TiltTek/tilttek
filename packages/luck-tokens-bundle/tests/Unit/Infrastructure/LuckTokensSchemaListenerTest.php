<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\LuckTokensSchemaListener;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\LuckTokensTables;

#[CoversClass(LuckTokensSchemaListener::class)]
#[CoversClass(LuckTokensTables::class)]
final class LuckTokensSchemaListenerTest extends TestCase
{
    public function testTheTablesJoinTheSchemaOfTheirConnectionOnly(): void
    {
        $tokens = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $other = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $event = new GenerateSchemaEventArgs($this->entityManager($tokens), Schema::editor()->create());
        new LuckTokensSchemaListener($tokens, false)->postGenerateSchema($event);
        self::assertSame([LuckTokensTables::ENTRIES], $this->names($event->getSchema()));

        $withRules = new GenerateSchemaEventArgs($this->entityManager($tokens), Schema::editor()->create());
        new LuckTokensSchemaListener($tokens, true)->postGenerateSchema($withRules);
        self::assertSame([LuckTokensTables::ENTRIES, LuckTokensTables::RULES], $this->names($withRules->getSchema()));

        $elsewhere = new GenerateSchemaEventArgs($this->entityManager($other), Schema::editor()->create());
        new LuckTokensSchemaListener($tokens, true)->postGenerateSchema($elsewhere);
        self::assertSame([], $elsewhere->getSchema()->getTables());
    }

    public function testATableTheSchemaHasIsLeftAsItIs(): void
    {
        $platform = new SQLitePlatform();
        $mine = Table::editor()->setUnquotedName(LuckTokensTables::RULES)->addColumn(Column::editor()->setUnquotedName('mine')->setTypeName('string')->setLength(10)->create())->create();
        $schema = Schema::editor()->addTable($mine)->create();

        $again = LuckTokensTables::configure($schema, $platform);

        self::assertSame([LuckTokensTables::ENTRIES, LuckTokensTables::RULES], $this->names($again));
        self::assertCount(1, $again->getTable(LuckTokensTables::RULES)->getColumns());
        self::assertTrue($again->getTable(LuckTokensTables::RULES)->hasColumn('mine'));
        self::assertEquals(LuckTokensTables::tables($platform, false)[LuckTokensTables::ENTRIES]->getColumns(), $again->getTable(LuckTokensTables::ENTRIES)->getColumns());
    }

    public function testTheTablesAsPostgreSqlCreatesThem(): void
    {
        $platform = new PostgreSQLPlatform();

        self::assertSame([
            'CREATE TABLE tilttek_luck_tokens_entry (table_ref VARCHAR(320) NOT NULL, position INT NOT NULL, operation_id VARCHAR(64) NOT NULL, entry_type VARCHAR(32) NOT NULL, token_kind VARCHAR(64) DEFAULT NULL, holder VARCHAR(320) NOT NULL, delta INT NOT NULL, balance INT NOT NULL, actor VARCHAR(320) NOT NULL, written_at VARCHAR(27) NOT NULL, reason VARCHAR(280) DEFAULT NULL, session_key VARCHAR(64) DEFAULT NULL, PRIMARY KEY (table_ref, position))',
            'CREATE INDEX tilttek_luck_tokens_entry_balance ON tilttek_luck_tokens_entry (table_ref, token_kind, holder, position)',
            'CREATE INDEX tilttek_luck_tokens_entry_operation ON tilttek_luck_tokens_entry (table_ref, operation_id, position)',
            'CREATE INDEX tilttek_luck_tokens_entry_session ON tilttek_luck_tokens_entry (table_ref, session_key)',
            'CREATE INDEX tilttek_luck_tokens_entry_holder ON tilttek_luck_tokens_entry (holder)',
            'CREATE INDEX tilttek_luck_tokens_entry_actor ON tilttek_luck_tokens_entry (actor)',
            'CREATE TABLE tilttek_luck_tokens_rules (table_ref VARCHAR(320) NOT NULL, rules TEXT NOT NULL, PRIMARY KEY (table_ref))',
        ], $platform->getCreateTablesSQL(LuckTokensTables::configure(Schema::editor()->create(), $platform)->getTables()));
    }

    public function testOnMySqlAndMariaDbTheTextsCompareByteByByte(): void
    {
        foreach ([new MySQLPlatform(), new MariaDBPlatform()] as $platform) {
            $sql = $platform->getCreateTablesSQL(array_values(LuckTokensTables::tables($platform)));

            self::assertSame([
                'CREATE TABLE tilttek_luck_tokens_entry (table_ref VARCHAR(320) NOT NULL COLLATE `utf8mb4_bin`, position INT NOT NULL, operation_id VARCHAR(64) NOT NULL COLLATE `utf8mb4_bin`, entry_type VARCHAR(32) NOT NULL COLLATE `utf8mb4_bin`, token_kind VARCHAR(64) DEFAULT NULL COLLATE `utf8mb4_bin`, holder VARCHAR(320) NOT NULL COLLATE `utf8mb4_bin`, delta INT NOT NULL, balance INT NOT NULL, actor VARCHAR(320) NOT NULL COLLATE `utf8mb4_bin`, written_at VARCHAR(27) NOT NULL COLLATE `utf8mb4_bin`, reason VARCHAR(280) DEFAULT NULL, session_key VARCHAR(64) DEFAULT NULL COLLATE `utf8mb4_bin`, INDEX tilttek_luck_tokens_entry_balance (table_ref, token_kind, holder, position), INDEX tilttek_luck_tokens_entry_operation (table_ref, operation_id, position), INDEX tilttek_luck_tokens_entry_session (table_ref, session_key), INDEX tilttek_luck_tokens_entry_holder (holder), INDEX tilttek_luck_tokens_entry_actor (actor), PRIMARY KEY (table_ref, position))',
                'CREATE TABLE tilttek_luck_tokens_rules (table_ref VARCHAR(320) NOT NULL COLLATE `utf8mb4_bin`, rules LONGTEXT NOT NULL, PRIMARY KEY (table_ref))',
            ], array_map(static fn (string $statement): string => preg_replace('/ DEFAULT CHARACTER SET .*$/', '', $statement) ?? $statement, $sql));
        }
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
