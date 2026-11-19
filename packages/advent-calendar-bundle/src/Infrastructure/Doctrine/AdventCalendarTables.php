<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The table of the openings, `tilttek_advent_calendar_opening`, added to the schema Doctrine generates so that the
 * application's migrations create it: one row per calendar, member and day — its primary key is the guard that makes
 * an opening happen once, even when two requests race —, with the moment it was opened, whether its `BoxOpened` was
 * delivered, and an index on the member
 * for their export and erasure. The openings are many and only ever read by these keys: they go through DBAL, not the
 * ORM.
 */
final class AdventCalendarTables
{
    public const string OPENINGS = 'tilttek_advent_calendar_opening';

    /** A resource reference: a kind of 64 bytes, a colon and an identifier of 255. */
    public const int REFERENCE_LENGTH = 320;

    private function __construct()
    {
    }

    /**
     * The schema with the table of the openings added, unless it has it already.
     */
    public static function configure(Schema $schema): Schema
    {
        if ($schema->hasTable(self::OPENINGS)) {
            return $schema;
        }

        $editor = $schema->edit();
        $editor->addTable(Table::editor()
            ->setUnquotedName(self::OPENINGS)
            ->addColumn(Column::editor()->setUnquotedName('calendar')->setTypeName(Types::STRING)->setLength(64)->create())
            ->addColumn(Column::editor()->setUnquotedName('member')->setTypeName(Types::STRING)->setLength(self::REFERENCE_LENGTH)->create())
            ->addColumn(Column::editor()->setUnquotedName('day')->setTypeName(Types::SMALLINT)->create())
            ->addColumn(Column::editor()->setUnquotedName('opened_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create())
            ->addColumn(Column::editor()->setUnquotedName('delivered')->setTypeName(Types::BOOLEAN)->setDefaultValue(false)->create())
            ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('calendar', 'member', 'day')->create())
            ->addIndex(Index::editor()->setUnquotedName('tilttek_advent_calendar_opening_member')->setUnquotedColumnNames('member')->create())
            ->create());

        return $editor->create();
    }
}
