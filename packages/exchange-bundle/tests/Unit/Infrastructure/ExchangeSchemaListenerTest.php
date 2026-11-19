<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Unit\Infrastructure;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\Event\GenerateSchemaEventArgs;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\ExchangeSchemaListener;
use Tilttek\ExchangeBundle\Infrastructure\Doctrine\ExchangeTables;

#[CoversClass(ExchangeSchemaListener::class)]
#[CoversClass(ExchangeTables::class)]
final class ExchangeSchemaListenerTest extends TestCase
{
    public function testTheTablesJoinTheSchemaOfTheirConnectionOnly(): void
    {
        $exchanges = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);
        $other = DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true]);

        $event = new GenerateSchemaEventArgs($this->entityManager($exchanges), Schema::editor()->create());
        new ExchangeSchemaListener($exchanges, false)->postGenerateSchema($event);
        self::assertSame(['tilttek_exchange_hold', 'tilttek_exchange_offer', 'tilttek_exchange_offer_item', 'tilttek_exchange_reservation', 'tilttek_exchange_stock_lock', 'tilttek_exchange_transfer'], $this->names($event->getSchema()));

        $withCatalog = new GenerateSchemaEventArgs($this->entityManager($exchanges), Schema::editor()->create());
        new ExchangeSchemaListener($exchanges, true)->postGenerateSchema($withCatalog);
        self::assertTrue($withCatalog->getSchema()->hasTable(ExchangeTables::REWARDS));

        $elsewhere = new GenerateSchemaEventArgs($this->entityManager($other), Schema::editor()->create());
        new ExchangeSchemaListener($exchanges, true)->postGenerateSchema($elsewhere);
        self::assertSame([], $elsewhere->getSchema()->getTables());
    }

    public function testATableTheSchemaHasIsLeftAsItIs(): void
    {
        $schema = ExchangeTables::configure(Schema::editor()->create());
        $again = ExchangeTables::configure($schema);

        self::assertCount(7, $again->getTables());
        self::assertSame($schema->getTable(ExchangeTables::REWARDS)->getColumns(), $again->getTable(ExchangeTables::REWARDS)->getColumns());
        self::assertTrue($again->getTable(ExchangeTables::TRANSFERS)->getColumn('position')->getAutoincrement());
        self::assertSame(IndexType::UNIQUE, $again->getTable(ExchangeTables::TRANSFERS)->getIndex('tilttek_exchange_transfer_key')->getType());
        self::assertFalse($again->getTable(ExchangeTables::RESERVATIONS)->getColumn('settled_at')->getNotnull());
        self::assertTrue($again->getTable(ExchangeTables::RESERVATIONS)->getColumn('expires_at')->getNotnull());
        self::assertSame(320, $again->getTable(ExchangeTables::HOLDS)->getColumn('item')->getLength());
    }

    public function testEveryMomentIsAColumnOfMomentsInUtc(): void
    {
        $schema = ExchangeTables::configure(Schema::editor()->create());
        $moments = [
            ExchangeTables::REWARDS => ['available_from', 'available_until'],
            ExchangeTables::RESERVATIONS => ['reserved_at', 'expires_at', 'settled_at'],
            ExchangeTables::TRANSFERS => ['transferred_at'],
            ExchangeTables::OFFERS => ['made_at', 'expires_at', 'closed_at'],
        ];

        foreach ($moments as $table => $columns) {
            foreach ($columns as $column) {
                self::assertSame(Types::DATETIME_UTC_IMMUTABLE, $schema->getTable($table)->getColumn($column)->getTypeName(), $table.'.'.$column);
            }
        }
    }

    public function testTheTablesAsPostgreSqlCreatesThem(): void
    {
        self::assertSame([
            'CREATE TABLE tilttek_exchange_reward (code VARCHAR(100) NOT NULL, price BIGINT NOT NULL, currency VARCHAR(32) DEFAULT NULL, stock BIGINT DEFAULT NULL, available_from TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, available_until TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, per_member BIGINT DEFAULT NULL, requires TEXT NOT NULL, PRIMARY KEY (code))',
            'CREATE TABLE tilttek_exchange_reservation (reservation_key VARCHAR(200) NOT NULL, member VARCHAR(320) NOT NULL, reward VARCHAR(100) NOT NULL, quantity BIGINT NOT NULL, amount BIGINT NOT NULL, currency VARCHAR(32) NOT NULL, status VARCHAR(16) NOT NULL, reserved_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, settled_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, PRIMARY KEY (reservation_key))',
            'CREATE INDEX tilttek_exchange_reservation_reward ON tilttek_exchange_reservation (reward, status)',
            'CREATE INDEX tilttek_exchange_reservation_member ON tilttek_exchange_reservation (member)',
            'CREATE INDEX tilttek_exchange_reservation_due ON tilttek_exchange_reservation (status, expires_at)',
            'CREATE TABLE tilttek_exchange_stock_lock (reward VARCHAR(100) NOT NULL, reservations BIGINT NOT NULL, PRIMARY KEY (reward))',
            'CREATE TABLE tilttek_exchange_transfer (position BIGINT GENERATED BY DEFAULT AS IDENTITY NOT NULL, transfer_key VARCHAR(200) NOT NULL, from_member VARCHAR(320) NOT NULL, to_member VARCHAR(320) NOT NULL, amount BIGINT NOT NULL, currency VARCHAR(32) NOT NULL, transferred_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (position))',
            'CREATE UNIQUE INDEX tilttek_exchange_transfer_key ON tilttek_exchange_transfer (transfer_key)',
            'CREATE INDEX tilttek_exchange_transfer_from ON tilttek_exchange_transfer (from_member, currency, transferred_at)',
            'CREATE INDEX tilttek_exchange_transfer_to ON tilttek_exchange_transfer (to_member)',
            'CREATE TABLE tilttek_exchange_offer (offer_key VARCHAR(200) NOT NULL, proposer VARCHAR(320) NOT NULL, recipient VARCHAR(320) NOT NULL, offered_points BIGINT NOT NULL, requested_points BIGINT NOT NULL, currency VARCHAR(32) NOT NULL, status VARCHAR(16) NOT NULL, made_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, closed_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, counters VARCHAR(200) DEFAULT NULL, PRIMARY KEY (offer_key))',
            'CREATE INDEX tilttek_exchange_offer_proposer ON tilttek_exchange_offer (proposer, made_at)',
            'CREATE INDEX tilttek_exchange_offer_recipient ON tilttek_exchange_offer (recipient)',
            'CREATE INDEX tilttek_exchange_offer_due ON tilttek_exchange_offer (status, expires_at)',
            'CREATE TABLE tilttek_exchange_offer_item (offer_key VARCHAR(200) NOT NULL, side VARCHAR(16) NOT NULL, position BIGINT NOT NULL, item VARCHAR(320) NOT NULL, PRIMARY KEY (offer_key, side, position))',
            'CREATE TABLE tilttek_exchange_hold (item VARCHAR(320) NOT NULL, offer_key VARCHAR(200) NOT NULL, PRIMARY KEY (item))',
            'CREATE INDEX tilttek_exchange_hold_offer ON tilttek_exchange_hold (offer_key)',
        ], new PostgreSQLPlatform()->getCreateTablesSQL(ExchangeTables::configure(Schema::editor()->create())->getTables()));
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
