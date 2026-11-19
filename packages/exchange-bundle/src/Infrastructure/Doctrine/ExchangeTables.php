<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Tilttek\Exchange\ExchangeKey;

/**
 * The tables of the exchanges, added to the schema Doctrine generates so that the application's migrations create
 * them:
 *
 * - `tilttek_exchange_reward`: the catalogue, when the application keeps it in the database;
 * - `tilttek_exchange_reservation`: one row per reservation, its key the primary key;
 * - `tilttek_exchange_stock_lock`: one row per reward with a stock or a limit, which each reservation updates to hold
 *   its lock while it counts the units held; it holds no stock, which is always counted from the reservations;
 * - `tilttek_exchange_transfer`: the journal of transfers, its position an auto-increment, its key unique;
 * - `tilttek_exchange_offer` and `tilttek_exchange_offer_item`: the offers and their items, in order on each side;
 * - `tilttek_exchange_hold`: one row per item an open offer holds, the item its primary key — what makes an item
 *   offered once at a time, even when two processes offer it at the same moment.
 */
final class ExchangeTables
{
    public const string REWARDS = 'tilttek_exchange_reward';

    public const string RESERVATIONS = 'tilttek_exchange_reservation';

    public const string STOCK_LOCKS = 'tilttek_exchange_stock_lock';

    public const string TRANSFERS = 'tilttek_exchange_transfer';

    public const string OFFERS = 'tilttek_exchange_offer';

    public const string OFFER_ITEMS = 'tilttek_exchange_offer_item';

    public const string HOLDS = 'tilttek_exchange_hold';

    /**
     * A member or an item as a `phil/resource` reference: a kind of 64 bytes, a colon and an identifier of 255.
     */
    public const int REFERENCE_LENGTH = 320;

    private function __construct()
    {
    }

    /**
     * The schema with the tables added, those it already has left as they are; the catalogue's table only when the
     * catalogue is kept in the database.
     */
    public static function configure(Schema $schema, bool $catalog = true): Schema
    {
        $editor = $schema->edit();
        $tables = self::tables($catalog);

        foreach ($tables as $name => $table) {
            if (!$schema->hasTable($name)) {
                $editor->addTable($table);
            }
        }

        return $editor->create();
    }

    /**
     * @return array<string, Table>
     */
    private static function tables(bool $catalog): array
    {
        $tables = [];

        if ($catalog) {
            $tables[self::REWARDS] = Table::editor()
                ->setUnquotedName(self::REWARDS)
                ->addColumn(self::string('code', 100))
                ->addColumn(self::bigint('price'))
                ->addColumn(self::string('currency', 32, true))
                ->addColumn(self::bigint('stock', true))
                ->addColumn(self::date('available_from', true))
                ->addColumn(self::date('available_until', true))
                ->addColumn(self::bigint('per_member', true))
                ->addColumn(Column::editor()->setUnquotedName('requires')->setTypeName(Types::TEXT)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('code')->create())
                ->create();
        }

        $tables[self::RESERVATIONS] = Table::editor()
            ->setUnquotedName(self::RESERVATIONS)
            ->addColumn(self::string('reservation_key', ExchangeKey::MAX_LENGTH))
            ->addColumn(self::string('member', self::REFERENCE_LENGTH))
            ->addColumn(self::string('reward', 100))
            ->addColumn(self::bigint('quantity'))
            ->addColumn(self::bigint('amount'))
            ->addColumn(self::string('currency', 32))
            ->addColumn(self::string('status', 16))
            ->addColumn(self::date('reserved_at'))
            ->addColumn(self::date('expires_at'))
            ->addColumn(self::date('settled_at', true))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('reservation_key')->create())
            ->addIndex(self::index(self::RESERVATIONS.'_reward', 'reward', 'status'))
            ->addIndex(self::index(self::RESERVATIONS.'_member', 'member'))
            ->addIndex(self::index(self::RESERVATIONS.'_due', 'status', 'expires_at'))
            ->create();

        $tables[self::STOCK_LOCKS] = Table::editor()
            ->setUnquotedName(self::STOCK_LOCKS)
            ->addColumn(self::string('reward', 100))
            ->addColumn(self::bigint('reservations'))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('reward')->create())
            ->create();

        $tables[self::TRANSFERS] = Table::editor()
            ->setUnquotedName(self::TRANSFERS)
            ->addColumn(Column::editor()->setUnquotedName('position')->setTypeName(Types::BIGINT)->setAutoincrement(true)->create())
            ->addColumn(self::string('transfer_key', ExchangeKey::MAX_LENGTH))
            ->addColumn(self::string('from_member', self::REFERENCE_LENGTH))
            ->addColumn(self::string('to_member', self::REFERENCE_LENGTH))
            ->addColumn(self::bigint('amount'))
            ->addColumn(self::string('currency', 32))
            ->addColumn(self::date('transferred_at'))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('position')->create())
            ->addIndex(Index::editor()->setUnquotedName(self::TRANSFERS.'_key')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('transfer_key')->create())
            ->addIndex(self::index(self::TRANSFERS.'_from', 'from_member', 'currency', 'transferred_at'))
            ->addIndex(self::index(self::TRANSFERS.'_to', 'to_member'))
            ->create();

        $tables[self::OFFERS] = Table::editor()
            ->setUnquotedName(self::OFFERS)
            ->addColumn(self::string('offer_key', ExchangeKey::MAX_LENGTH))
            ->addColumn(self::string('proposer', self::REFERENCE_LENGTH))
            ->addColumn(self::string('recipient', self::REFERENCE_LENGTH))
            ->addColumn(self::bigint('offered_points'))
            ->addColumn(self::bigint('requested_points'))
            ->addColumn(self::string('currency', 32))
            ->addColumn(self::string('status', 16))
            ->addColumn(self::date('made_at'))
            ->addColumn(self::date('expires_at'))
            ->addColumn(self::date('closed_at', true))
            ->addColumn(self::string('counters', ExchangeKey::MAX_LENGTH, true))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('offer_key')->create())
            ->addIndex(self::index(self::OFFERS.'_proposer', 'proposer', 'made_at'))
            ->addIndex(self::index(self::OFFERS.'_recipient', 'recipient'))
            ->addIndex(self::index(self::OFFERS.'_due', 'status', 'expires_at'))
            ->create();

        $tables[self::OFFER_ITEMS] = Table::editor()
            ->setUnquotedName(self::OFFER_ITEMS)
            ->addColumn(self::string('offer_key', ExchangeKey::MAX_LENGTH))
            ->addColumn(self::string('side', 16))
            ->addColumn(self::bigint('position'))
            ->addColumn(self::string('item', self::REFERENCE_LENGTH))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('offer_key', 'side', 'position')->create())
            ->create();

        $tables[self::HOLDS] = Table::editor()
            ->setUnquotedName(self::HOLDS)
            ->addColumn(self::string('item', self::REFERENCE_LENGTH))
            ->addColumn(self::string('offer_key', ExchangeKey::MAX_LENGTH))
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('item')->create())
            ->addIndex(self::index(self::HOLDS.'_offer', 'offer_key'))
            ->create();

        return $tables;
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length, bool $nullable = false): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull(!$nullable)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function bigint(string $name, bool $nullable = false): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::BIGINT)->setNotNull(!$nullable)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function date(string $name, bool $nullable = false): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::DATETIME_UTC_IMMUTABLE)->setNotNull(!$nullable)->create();
    }

    /**
     * @param non-empty-string $name
     * @param non-empty-string ...$columns
     */
    private static function index(string $name, string ...$columns): Index
    {
        return Index::editor()->setUnquotedName($name)->setUnquotedColumnNames(...$columns)->create();
    }
}
