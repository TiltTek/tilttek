<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Tilttek\LuckTokens\Ledger\LedgerEntry;

/**
 * The tables of the tokens, added to the schema Doctrine generates so that the application's migrations create them:
 *
 * - `tilttek_luck_tokens_entry`: the ledgers, one row per entry, the table and the position its primary key — what
 *   refuses a second writer after the same position —, the balance each entry leaves indexed by table, kind, holder
 *   and position, so that a balance is the row of the holder's last entry of the kind; the entries of an operation
 *   indexed with their positions, read in order without a sort; the sessions, the holders and the actors indexed as
 *   well;
 * - `tilttek_luck_tokens_rules`: the rules of each table as JSON, when the application keeps them in the database.
 *
 * On MySQL and MariaDB, the text columns compare byte by byte, as the references and identifiers do in PHP.
 */
final class LuckTokensTables
{
    public const string ENTRIES = 'tilttek_luck_tokens_entry';

    public const string RULES = 'tilttek_luck_tokens_rules';

    /**
     * A table, a holder or an actor as a `phil/resource` reference: a kind of 64 bytes, a colon and an identifier of 255.
     */
    public const int REFERENCE_LENGTH = 320;

    /**
     * An operation or a session identifier, as `LedgerEntry::identifier()` takes it.
     */
    public const int IDENTIFIER_LENGTH = 64;

    /**
     * A time as the library writes it, `2026-11-13T20:15:03.250000Z`: in UTC to the microsecond, sorted as text.
     */
    public const int TIME_LENGTH = 27;

    private function __construct()
    {
    }

    /**
     * The schema with the tables added, those it already has left as they are; the rules' table only when the rules
     * are kept in the database.
     */
    public static function configure(Schema $schema, AbstractPlatform $platform, bool $rules = true): Schema
    {
        $editor = $schema->edit();

        foreach (self::tables($platform, $rules) as $name => $table) {
            if (!$schema->hasTable($name)) {
                $editor->addTable($table);
            }
        }

        return $editor->create();
    }

    /**
     * @return array<string, Table>
     */
    public static function tables(AbstractPlatform $platform, bool $rules = true): array
    {
        $binary = $platform instanceof AbstractMySQLPlatform ? 'utf8mb4_bin' : null;

        $tables = [
            self::ENTRIES => Table::editor()
                ->setUnquotedName(self::ENTRIES)
                ->addColumn(self::string('table_ref', self::REFERENCE_LENGTH, $binary))
                ->addColumn(self::integer('position'))
                ->addColumn(self::string('operation_id', self::IDENTIFIER_LENGTH, $binary))
                ->addColumn(self::string('entry_type', 32, $binary))
                ->addColumn(self::string('token_kind', 64, $binary, true))
                ->addColumn(self::string('holder', self::REFERENCE_LENGTH, $binary))
                ->addColumn(self::integer('delta'))
                ->addColumn(self::integer('balance'))
                ->addColumn(self::string('actor', self::REFERENCE_LENGTH, $binary))
                ->addColumn(self::string('written_at', self::TIME_LENGTH, $binary))
                ->addColumn(self::string('reason', LedgerEntry::MAX_REASON_LENGTH, null, true))
                ->addColumn(self::string('session_key', self::IDENTIFIER_LENGTH, $binary, true))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('table_ref', 'position')->create())
                ->addIndex(self::index(self::ENTRIES.'_balance', 'table_ref', 'token_kind', 'holder', 'position'))
                ->addIndex(self::index(self::ENTRIES.'_operation', 'table_ref', 'operation_id', 'position'))
                ->addIndex(self::index(self::ENTRIES.'_session', 'table_ref', 'session_key'))
                ->addIndex(self::index(self::ENTRIES.'_holder', 'holder'))
                ->addIndex(self::index(self::ENTRIES.'_actor', 'actor'))
                ->create(),
        ];

        if ($rules) {
            $tables[self::RULES] = Table::editor()
                ->setUnquotedName(self::RULES)
                ->addColumn(self::string('table_ref', self::REFERENCE_LENGTH, $binary))
                ->addColumn(Column::editor()->setUnquotedName('rules')->setTypeName(Types::TEXT)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('table_ref')->create())
                ->create();
        }

        return $tables;
    }

    /**
     * @param non-empty-string      $name
     * @param non-empty-string|null $collation
     */
    private static function string(string $name, int $length, ?string $collation, bool $nullable = false): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::STRING)->setLength($length)->setCollation($collation)->setNotNull(!$nullable)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function integer(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::INTEGER)->create();
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
