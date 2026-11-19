<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The five tables of the escape rooms, added to the schema Doctrine generates so that the application's migrations
 * create them:
 *
 * - `tilttek_escape_room_game`: each game as the plain values `Game::toArray()` writes, in UTC, with its room, team and
 *   session, and the moment its countdown reaches zero — indexed, for the games due;
 * - `tilttek_escape_room_result`: the result of each game a team escaped, and whether the team agreed to show it,
 *   indexed in the order of the leaderboard;
 * - `tilttek_escape_room_screen`: what the hint screen of each game shows, one row per game;
 * - `tilttek_escape_room_booking`: the bookings of the sessions, in the order they were made in each session;
 * - `tilttek_escape_room_session_lock`: one row per session booked, which a booking locks while it decides — the lock
 *   that keeps two parties from both getting the last seats.
 */
final class EscapeRoomTables
{
    public const string GAMES = 'tilttek_escape_room_game';

    public const string RESULTS = 'tilttek_escape_room_result';

    public const string SCREENS = 'tilttek_escape_room_screen';

    public const string BOOKINGS = 'tilttek_escape_room_booking';

    public const string SESSION_LOCKS = 'tilttek_escape_room_session_lock';

    /**
     * A resource reference: a kind of 64 bytes, a colon and an identifier of 255.
     */
    public const int REFERENCE_LENGTH = 320;

    /**
     * The identifier of a session or a booking, chosen by the application.
     */
    public const int IDENTIFIER_LENGTH = 255;

    /**
     * A message of the host: 280 characters of up to four bytes.
     */
    public const int MESSAGE_LENGTH = 1_120;

    private function __construct()
    {
    }

    /**
     * The schema with the five tables added, those it already has left as they are.
     */
    public static function configure(Schema $schema): Schema
    {
        $editor = $schema->edit();

        if (!$schema->hasTable(self::GAMES)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::GAMES)
                ->addColumn(self::string('id', 64))
                ->addColumn(self::string('room', 64))
                ->addColumn(self::string('team', self::REFERENCE_LENGTH))
                ->addColumn(self::nullable(self::IDENTIFIER_LENGTH, 'session'))
                ->addColumn(Column::editor()->setUnquotedName('deadline')->setTypeName(Types::DATETIME_UTC_IMMUTABLE)->setNotNull(false)->create())
                ->addColumn(Column::editor()->setUnquotedName('state')->setTypeName(Types::TEXT)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(self::index(self::GAMES.'_deadline', 'deadline'))
                ->addIndex(self::index(self::GAMES.'_team', 'team'))
                ->create());
        }

        if (!$schema->hasTable(self::RESULTS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::RESULTS)
                ->addColumn(self::string('game', 64))
                ->addColumn(self::string('room', 64))
                ->addColumn(self::string('team', self::REFERENCE_LENGTH))
                ->addColumn(self::nullable(self::IDENTIFIER_LENGTH, 'session'))
                ->addColumn(self::integer('final_seconds'))
                ->addColumn(self::integer('played_seconds'))
                ->addColumn(self::integer('penalty_seconds'))
                ->addColumn(self::integer('hints'))
                ->addColumn(self::integer('skips'))
                ->addColumn(self::date('escaped_at'))
                ->addColumn(Column::editor()->setUnquotedName('escaped_micro')->setTypeName(Types::INTEGER)->create())
                ->addColumn(Column::editor()->setUnquotedName('public')->setTypeName(Types::BOOLEAN)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('game')->create())
                ->addIndex(self::index(self::RESULTS.'_rank', 'room', 'public', 'final_seconds', 'hints', 'escaped_at', 'escaped_micro'))
                ->addIndex(self::index(self::RESULTS.'_team', 'team'))
                ->create());
        }

        if (!$schema->hasTable(self::SCREENS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::SCREENS)
                ->addColumn(self::string('game', 64))
                ->addColumn(self::string('kind', 16))
                ->addColumn(self::string('host', self::REFERENCE_LENGTH))
                ->addColumn(self::date('shown_at'))
                ->addColumn(self::nullable(255, 'puzzle'))
                ->addColumn(Column::editor()->setUnquotedName('tier')->setTypeName(Types::SMALLINT)->setNotNull(false)->create())
                ->addColumn(self::integer('penalty'))
                ->addColumn(self::nullable(self::MESSAGE_LENGTH, 'message'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('game')->create())
                ->addIndex(self::index(self::SCREENS.'_host', 'host'))
                ->create());
        }

        if (!$schema->hasTable(self::BOOKINGS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::BOOKINGS)
                ->addColumn(self::string('id', self::IDENTIFIER_LENGTH))
                ->addColumn(self::string('session', self::IDENTIFIER_LENGTH))
                ->addColumn(self::string('room', 64))
                ->addColumn(self::string('party', self::REFERENCE_LENGTH))
                ->addColumn(Column::editor()->setUnquotedName('seats')->setTypeName(Types::SMALLINT)->create())
                ->addColumn(Column::editor()->setUnquotedName('private')->setTypeName(Types::BOOLEAN)->create())
                ->addColumn(self::integer('position'))
                ->addColumn(self::date('booked_at'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(self::index(self::BOOKINGS.'_session', 'session', 'position'))
                ->addIndex(self::index(self::BOOKINGS.'_party', 'party'))
                ->create());
        }

        if (!$schema->hasTable(self::SESSION_LOCKS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::SESSION_LOCKS)
                ->addColumn(self::string('session', self::IDENTIFIER_LENGTH))
                ->addColumn(self::date('locked_at'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('session')->create())
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
    private static function nullable(int $length, string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull(false)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function integer(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::BIGINT)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function date(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::DATETIME_UTC_IMMUTABLE)->create();
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
