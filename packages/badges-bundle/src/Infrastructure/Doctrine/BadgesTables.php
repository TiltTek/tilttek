<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;
use Tilttek\Badges\Catalog;

/**
 * The four tables of the badges, added to the schema Doctrine generates so that the application's migrations create
 * them:
 *
 * - `tilttek_badges_contribution`: what each activity brought to each criterion of each badge, one row per badge,
 *   path and activity — the unique index is what makes an activity count once, even when two processes write it;
 * - `tilttek_badges_award`: the members' awards, one row per badge, member and number;
 * - `tilttek_badges_profile`: what each member shows — the badges, the showcase, the title —, one row per member who
 *   shows a badge;
 * - `tilttek_badges_grant`: the applications each member allows to read their badges.
 */
final class BadgesTables
{
    public const string CONTRIBUTIONS = 'tilttek_badges_contribution';

    public const string AWARDS = 'tilttek_badges_award';

    public const string PROFILES = 'tilttek_badges_profile';

    public const string GRANTS = 'tilttek_badges_grant';

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

        if (!$schema->hasTable(self::CONTRIBUTIONS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::CONTRIBUTIONS)
                ->addColumn(self::string('badge', 64))
                ->addColumn(self::string('path', Catalog::MAX_PATH_LENGTH))
                ->addColumn(self::string('activity', 255))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(Column::editor()->setUnquotedName('quantity')->setTypeName(Types::INTEGER)->create())
                ->addColumn(Column::editor()->setUnquotedName('occurred_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('badge', 'path', 'activity')->create())
                ->addIndex(Index::editor()->setUnquotedName(self::CONTRIBUTIONS.'_member')->setUnquotedColumnNames('member', 'badge', 'path')->create())
                ->addIndex(Index::editor()->setUnquotedName(self::CONTRIBUTIONS.'_activity')->setUnquotedColumnNames('activity')->create())
                ->addIndex(Index::editor()->setUnquotedName(self::CONTRIBUTIONS.'_occurred')->setUnquotedColumnNames('occurred_at')->create())
                ->create());
        }

        if (!$schema->hasTable(self::AWARDS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::AWARDS)
                ->addColumn(self::string('badge', 64))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(Column::editor()->setUnquotedName('number')->setTypeName(Types::INTEGER)->create())
                ->addColumn(Column::editor()->setUnquotedName('awarded_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create())
                ->addColumn(Column::editor()->setUnquotedName('cause')->setTypeName(Types::STRING)->setLength(255)->setNotNull(false)->create())
                ->addColumn(Column::editor()->setUnquotedName('manual')->setTypeName(Types::BOOLEAN)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('member', 'badge', 'number')->create())
                ->addIndex(Index::editor()->setUnquotedName(self::AWARDS.'_awarded')->setUnquotedColumnNames('manual', 'awarded_at')->create())
                ->create());
        }

        if (!$schema->hasTable(self::PROFILES)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::PROFILES)
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(Column::editor()->setUnquotedName('shown')->setTypeName(Types::JSON)->create())
                ->addColumn(Column::editor()->setUnquotedName('featured')->setTypeName(Types::JSON)->create())
                ->addColumn(Column::editor()->setUnquotedName('title')->setTypeName(Types::STRING)->setLength(64)->setNotNull(false)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('member')->create())
                ->create());
        }

        if (!$schema->hasTable(self::GRANTS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::GRANTS)
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::string('client', 64))
                ->addColumn(Column::editor()->setUnquotedName('granted_at')->setTypeName(Types::DATETIME_IMMUTABLE)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('member', 'client')->create())
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
