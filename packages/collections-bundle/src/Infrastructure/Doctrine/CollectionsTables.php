<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The six tables of the collections, added to the schema Doctrine generates so that the application's migrations
 * create them:
 *
 * - `tilttek_collections_copy`: the copies, one row per identifier — the primary key is what makes a key give one
 *   copy, even when two processes give it;
 * - `tilttek_collections_completion`: the sets completed, one row per set and member;
 * - `tilttek_collections_opening`: the packs opened, by key, with their seed, commitment, client seed, items and copies;
 * - `tilttek_collections_choice`: the dice the members chose, one row per member and number of sides;
 * - `tilttek_collections_daily`: the calendar of the item of the day once generated or chosen, one row per day;
 * - `tilttek_collections_definition`: the catalog itself, for a catalog kept in the database, each definition as JSON.
 */
final class CollectionsTables
{
    public const string COPIES = 'tilttek_collections_copy';

    public const string COMPLETIONS = 'tilttek_collections_completion';

    public const string OPENINGS = 'tilttek_collections_opening';

    public const string CHOICES = 'tilttek_collections_choice';

    public const string DAILY = 'tilttek_collections_daily';

    public const string DEFINITIONS = 'tilttek_collections_definition';

    /**
     * A resource reference: a kind of 64 bytes, a colon and an identifier of 255.
     */
    public const int REFERENCE_LENGTH = 320;

    /**
     * A key: the application's 200 bytes, or a key the library derives from it.
     */
    public const int KEY_LENGTH = 300;

    private function __construct()
    {
    }

    /**
     * The schema with the six tables added, those it already has left as they are.
     */
    public static function configure(Schema $schema): Schema
    {
        $editor = $schema->edit();

        if (!$schema->hasTable(self::COPIES)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::COPIES)
                ->addColumn(self::string('id', 32))
                ->addColumn(self::string('item', 64))
                ->addColumn(self::string('owner', self::REFERENCE_LENGTH))
                ->addColumn(self::string('source', 16))
                ->addColumn(self::string('copy_key', self::KEY_LENGTH))
                ->addColumn(self::date('acquired_at'))
                ->addColumn(self::boolean('tradable'))
                ->addColumn(self::boolean('moved'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('id')->create())
                ->addIndex(self::index(self::COPIES.'_owner', 'owner', 'acquired_at'))
                ->addIndex(self::index(self::COPIES.'_source', 'source', 'acquired_at'))
                ->create());
        }

        if (!$schema->hasTable(self::COMPLETIONS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::COMPLETIONS)
                ->addColumn(self::string('set_code', 64))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::date('completed_at'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('set_code', 'member')->create())
                ->addIndex(self::index(self::COMPLETIONS.'_member', 'member'))
                ->create());
        }

        if (!$schema->hasTable(self::OPENINGS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::OPENINGS)
                ->addColumn(self::string('opening_key', 200))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::string('pack', 64))
                ->addColumn(self::string('seed', 128))
                ->addColumn(self::string('commitment', 64))
                ->addColumn(self::text('client_seed'))
                ->addColumn(self::text('items'))
                ->addColumn(self::text('copies'))
                ->addColumn(self::date('opened_at'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('opening_key')->create())
                ->addIndex(self::index(self::OPENINGS.'_member', 'member'))
                ->create());
        }

        if (!$schema->hasTable(self::CHOICES)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::CHOICES)
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(Column::editor()->setUnquotedName('sides')->setTypeName(Types::INTEGER)->create())
                ->addColumn(self::string('copy_id', 32))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('member', 'sides')->create())
                ->create());
        }

        if (!$schema->hasTable(self::DAILY)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::DAILY)
                ->addColumn(self::string('day', 10))
                ->addColumn(self::string('item', 64))
                ->addColumn(self::string('origin', 16))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('day')->create())
                ->create());
        }

        if (!$schema->hasTable(self::DEFINITIONS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::DEFINITIONS)
                ->addColumn(self::string('kind', 16))
                ->addColumn(self::string('code', 64))
                ->addColumn(Column::editor()->setUnquotedName('position')->setTypeName(Types::INTEGER)->create())
                ->addColumn(self::text('definition'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('kind', 'code')->create())
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

    /**
     * @param non-empty-string $name
     */
    private static function boolean(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::BOOLEAN)->create();
    }

    /**
     * @param non-empty-string $name
     */
    private static function date(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::DATETIME_IMMUTABLE)->create();
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
