<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\Index\IndexType;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Tilttek\Activity\IdempotencyKey;
use Tilttek\Activity\Verb;

/**
 * The two tables of the journal, added to the schema Doctrine generates so that the application's migrations create
 * them:
 *
 * - `tilttek_activity_entry`: one row per entry, its position an auto-increment that never gives a number twice, one
 *   row per idempotency key and kind — the unique index is what makes an activity count once, even when two
 *   processes record it at the same moment;
 * - `tilttek_activity_checkpoint`: where each projection stopped reading.
 */
final class ActivityTables
{
    public const string ENTRIES = 'tilttek_activity_entry';

    public const string CHECKPOINTS = 'tilttek_activity_checkpoint';

    /**
     * A resource reference: a kind of 64 bytes, a colon and an identifier of 255.
     */
    public const int REFERENCE_LENGTH = 320;

    private function __construct()
    {
    }

    /**
     * The schema with the two tables added, those it already has left as they are.
     */
    public static function configure(Schema $schema): Schema
    {
        $editor = $schema->edit();

        if (!$schema->hasTable(self::ENTRIES)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::ENTRIES)
                ->addColumn(Column::editor()->setUnquotedName('position')->setTypeName(Types::BIGINT)->setAutoincrement(true)->create())
                ->addColumn(self::string('kind', 16))
                ->addColumn(self::string('idempotency_key', IdempotencyKey::MAX_LENGTH))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::string('verb', Verb::MAX_LENGTH))
                ->addColumn(Column::editor()->setUnquotedName('object')->setTypeName(Types::STRING)->setLength(self::REFERENCE_LENGTH)->setNotNull(false)->create())
                ->addColumn(Column::editor()->setUnquotedName('quantity')->setTypeName(Types::INTEGER)->create())
                ->addColumn(Column::editor()->setUnquotedName('context')->setTypeName(Types::JSON)->create())
                ->addColumn(Column::editor()->setUnquotedName('occurred_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create())
                ->addColumn(Column::editor()->setUnquotedName('recorded_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('position')->create())
                ->addIndex(Index::editor()->setUnquotedName(self::ENTRIES.'_key')->setType(IndexType::UNIQUE)->setUnquotedColumnNames('idempotency_key', 'kind')->create())
                ->addIndex(Index::editor()->setUnquotedName(self::ENTRIES.'_member')->setUnquotedColumnNames('member')->create())
                ->addIndex(Index::editor()->setUnquotedName(self::ENTRIES.'_object')->setUnquotedColumnNames('object')->create())
                ->addIndex(Index::editor()->setUnquotedName(self::ENTRIES.'_verb')->setUnquotedColumnNames('verb', 'occurred_at')->create())
                ->create());
        }

        if (!$schema->hasTable(self::CHECKPOINTS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::CHECKPOINTS)
                ->addColumn(self::string('projection', 64))
                ->addColumn(Column::editor()->setUnquotedName('position')->setTypeName(Types::BIGINT)->create())
                ->addColumn(Column::editor()->setUnquotedName('updated_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('projection')->create())
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
