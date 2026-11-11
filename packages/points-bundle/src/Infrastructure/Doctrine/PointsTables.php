<?php

declare(strict_types=1);

namespace Tilttek\PointsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Tilttek\Points\Account;
use Tilttek\Points\MovementKey;

/**
 * The two tables of the ledger, added to the schema Doctrine generates so that the application's migrations create
 * them:
 *
 * - `tilttek_points_movement`: one row per movement, its position an auto-increment, its key unique — the unique
 *   index is what makes a movement written once, even when two processes write it at the same moment;
 * - `tilttek_points_lock`: one row per account and currency that has paid a spending or a transfer, which each payment
 *   updates — counting the payments — to hold its lock while it checks the balance and writes the movement. It holds
 *   no balance: a balance is always the sum of the movements.
 */
final class PointsTables
{
    public const string MOVEMENTS = 'tilttek_points_movement';

    public const string LOCKS = 'tilttek_points_lock';

    /**
     * An activity's idempotency key, the cause of the points it earned.
     */
    public const int CAUSE_LENGTH = 255;

    private function __construct()
    {
    }

    /**
     * The schema with the two tables added, those it already has left as they are.
     */
    public static function configure(Schema $schema): Schema
    {
        $editor = $schema->edit();

        if (!$schema->hasTable(self::MOVEMENTS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::MOVEMENTS)
                ->addColumn(Column::editor()->setUnquotedName('position')->setTypeName(Types::BIGINT)->setAutoincrement(true)->create())
                ->addColumn(self::string('movement_key', MovementKey::MAX_LENGTH))
                ->addColumn(self::string('kind', 16))
                ->addColumn(self::string('currency', 32))
                ->addColumn(self::string('debit', Account::MAX_LENGTH))
                ->addColumn(self::string('credit', Account::MAX_LENGTH))
                ->addColumn(Column::editor()->setUnquotedName('amount')->setTypeName(Types::BIGINT)->create())
                ->addColumn(Column::editor()->setUnquotedName('occurred_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create())
                ->addColumn(Column::editor()->setUnquotedName('recorded_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create())
                ->addColumn(Column::editor()->setUnquotedName('cause')->setTypeName(Types::STRING)->setLength(self::CAUSE_LENGTH)->setNotNull(false)->create())
                ->addColumn(Column::editor()->setUnquotedName('reverses')->setTypeName(Types::STRING)->setLength(MovementKey::MAX_LENGTH)->setNotNull(false)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('position')->create())
                ->addIndex(Index::editor()->setUnquotedName(self::MOVEMENTS.'_key')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('movement_key')->create())
                ->addIndex(Index::editor()->setUnquotedName(self::MOVEMENTS.'_debit')->setUnquotedColumnNames('debit', 'currency')->create())
                ->addIndex(Index::editor()->setUnquotedName(self::MOVEMENTS.'_credit')->setUnquotedColumnNames('credit', 'currency')->create())
                ->addIndex(Index::editor()->setUnquotedName(self::MOVEMENTS.'_cause')->setUnquotedColumnNames('cause')->create())
                ->addIndex(Index::editor()->setUnquotedName(self::MOVEMENTS.'_kind')->setUnquotedColumnNames('kind', 'occurred_at')->create())
                ->create());
        }

        if (!$schema->hasTable(self::LOCKS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::LOCKS)
                ->addColumn(self::string('account', Account::MAX_LENGTH))
                ->addColumn(self::string('currency', 32))
                ->addColumn(Column::editor()->setUnquotedName('payments')->setTypeName(Types::BIGINT)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('account', 'currency')->create())
                ->create());
        }

        return $editor->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->create();
    }
}
