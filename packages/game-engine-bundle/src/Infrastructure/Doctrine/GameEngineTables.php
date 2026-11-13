<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The two tables of the logs, added to the schema Doctrine generates so that the application's migrations create them:
 *
 * - `tilttek_game_engine_table`: one row per table — its game and the version of its rules, its seed, its seats and
 *   options written as JSON, the number of its moves and its revision, which every append and removal compares and
 *   changes in one statement — the revision counts every change and never goes down;
 * - `tilttek_game_engine_move`: the moves, one row each, under the table and the move's number — the primary key is
 *   what keeps two moves of one number out, even when two processes write them.
 *
 * The seats, options and payloads are JSON in text columns, written and read by the store, so that no database
 * reorders the keys of a mapping a game reads in order.
 */
final class GameEngineTables
{
    public const string TABLES = 'tilttek_game_engine_table';

    public const string MOVES = 'tilttek_game_engine_move';

    /** The longest identifier of a table or a seat, as the library allows it. */
    public const int IDENTIFIER_LENGTH = 128;

    /** The longest name of a game, a version or a move type, as the library allows it. */
    public const int NAME_LENGTH = 64;

    private function __construct()
    {
    }

    /**
     * The schema with the two tables added, those it already has left as they are.
     */
    public static function configure(Schema $schema): Schema
    {
        $editor = $schema->edit();

        if (!$schema->hasTable(self::TABLES)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::TABLES)
                ->addColumn(self::string('game_id', self::IDENTIFIER_LENGTH))
                ->addColumn(self::string('game', self::NAME_LENGTH))
                ->addColumn(self::string('version', self::NAME_LENGTH))
                ->addColumn(self::string('seed', 64))
                ->addColumn(self::text('seats'))
                ->addColumn(self::text('options'))
                ->addColumn(Column::editor()->setUnquotedName('moves')->setTypeName(Types::INTEGER)->create())
                ->addColumn(Column::editor()->setUnquotedName('revision')->setTypeName(Types::BIGINT)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('game_id')->create())
                ->create());
        }

        if (!$schema->hasTable(self::MOVES)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::MOVES)
                ->addColumn(self::string('game_id', self::IDENTIFIER_LENGTH))
                ->addColumn(Column::editor()->setUnquotedName('number')->setTypeName(Types::INTEGER)->create())
                ->addColumn(self::string('type', self::NAME_LENGTH))
                ->addColumn(Column::editor()->setUnquotedName('seat')->setTypeName(Types::STRING)->setLength(self::IDENTIFIER_LENGTH)->setNotNull(false)->create())
                ->addColumn(self::text('payload'))
                ->addColumn(Column::editor()->setUnquotedName('drew')->setTypeName(Types::BOOLEAN)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('game_id', 'number')->create())
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

    /**
     * @param non-empty-string $name
     */
    private static function text(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::TEXT)->create();
    }
}
