<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The four tables of the safety tools, added to the schema Doctrine generates so that the application's migrations
 * create them:
 *
 * - `tilttek_table_safety_table`: each table's settings, as JSON;
 * - `tilttek_table_safety_participation`: each participant's lines and veils, checklist and session zero at each
 *   table, as JSON, one row per table and participant;
 * - `tilttek_table_safety_signal`: the signals raised during play — the author's reference only on a named signal —,
 *   ordered by the microsecond they were raised at;
 * - `tilttek_table_safety_feedback`: the stars and wishes of each session, one row per table, session and participant.
 */
final class TableSafetyTables
{
    public const string TABLES = 'tilttek_table_safety_table';

    public const string PARTICIPATIONS = 'tilttek_table_safety_participation';

    public const string SIGNALS = 'tilttek_table_safety_signal';

    public const string FEEDBACK = 'tilttek_table_safety_feedback';

    /**
     * A resource reference: a kind of 64 bytes, a colon and an identifier of 255.
     */
    public const int REFERENCE_LENGTH = 320;

    private function __construct()
    {
    }

    /**
     * The schema with the four tables added, those it already has left as they are.
     */
    public static function configure(Schema $schema): Schema
    {
        $editor = $schema->edit();

        if (!$schema->hasTable(self::TABLES)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::TABLES)
                ->addColumn(self::string('table_ref', self::REFERENCE_LENGTH))
                ->addColumn(self::text('settings'))
                ->addPrimaryKeyConstraint(self::primary('table_ref'))
                ->create());
        }

        if (!$schema->hasTable(self::PARTICIPATIONS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::PARTICIPATIONS)
                ->addColumn(self::string('table_ref', self::REFERENCE_LENGTH))
                ->addColumn(self::string('participant', self::REFERENCE_LENGTH))
                ->addColumn(self::text('lines_and_veils'))
                ->addColumn(self::text('consent'))
                ->addColumn(self::text('session_zero'))
                ->addColumn(self::date('first_answered_at'))
                ->addColumn(self::date('updated_at'))
                ->addPrimaryKeyConstraint(self::primary('table_ref', 'participant'))
                ->addIndex(self::index('tilttek_table_safety_participation_participant', 'participant'))
                ->create());
        }

        if (!$schema->hasTable(self::SIGNALS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::SIGNALS)
                ->addColumn(self::string('id', 32))
                ->addColumn(self::string('table_ref', self::REFERENCE_LENGTH))
                ->addColumn(self::string('kind', 32))
                ->addColumn(self::string('audience', 16))
                ->addColumn(self::date('raised_at'))
                ->addColumn(Column::editor()->setUnquotedName('raised_us')->setTypeName(Types::BIGINT)->create())
                ->addColumn(self::string('raised_by', self::REFERENCE_LENGTH, false))
                ->addColumn(self::date('acknowledged_at', false))
                ->addPrimaryKeyConstraint(self::primary('id'))
                ->addIndex(self::index('tilttek_table_safety_signal_table', 'table_ref', 'raised_us'))
                ->addIndex(self::index('tilttek_table_safety_signal_raised_by', 'raised_by'))
                ->create());
        }

        if (!$schema->hasTable(self::FEEDBACK)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::FEEDBACK)
                ->addColumn(self::string('table_ref', self::REFERENCE_LENGTH))
                ->addColumn(self::string('session', 64))
                ->addColumn(self::string('participant', self::REFERENCE_LENGTH))
                ->addColumn(self::text('feedback'))
                ->addColumn(self::date('given_at'))
                ->addPrimaryKeyConstraint(self::primary('table_ref', 'session', 'participant'))
                ->addIndex(self::index('tilttek_table_safety_feedback_participant', 'participant'))
                ->create());
        }

        return $editor->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function string(string $name, int $length, bool $notNull = true): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setNotNull($notNull)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function text(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::TEXT)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function date(string $name, bool $notNull = true): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::DATETIME_IMMUTABLE)->setNotNull($notNull)->create();
    }

    /**
     * @param non-empty-string ...$columns
     */
    private static function primary(string ...$columns): PrimaryKeyConstraint
    {
        return PrimaryKeyConstraint::editor()->setUnquotedColumnNames(...$columns)->create();
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
