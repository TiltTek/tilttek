<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The five tables of the progression, added to the schema Doctrine generates so that the application's migrations
 * create them:
 *
 * - `tilttek_progression_gain`: the experience each activity brought, one row per track, rule and activity — the
 *   primary key is what makes an activity count once, even when two processes write it;
 * - `tilttek_progression_milestone`: the milestones reached, one row per track, milestone, subject and season;
 * - `tilttek_progression_streak_gesture`: the activities that made a day or a week of a streak active, one row per
 *   streak and activity;
 * - `tilttek_progression_streak_freeze`: the units the members froze;
 * - `tilttek_progression_streak_state`: the run each member's streak was on, to tell when it breaks.
 */
final class ProgressionTables
{
    public const string GAINS = 'tilttek_progression_gain';

    public const string MILESTONES = 'tilttek_progression_milestone';

    public const string GESTURES = 'tilttek_progression_streak_gesture';

    public const string FREEZES = 'tilttek_progression_streak_freeze';

    public const string STATES = 'tilttek_progression_streak_state';

    /**
     * A resource reference: a kind of 64 bytes, a colon and an identifier of 255.
     */
    public const int REFERENCE_LENGTH = 320;

    private function __construct()
    {
    }

    /**
     * The schema with the five tables added, those it already has left as they are.
     */
    public static function configure(Schema $schema): Schema
    {
        $editor = $schema->edit();

        if (!$schema->hasTable(self::GAINS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::GAINS)
                ->addColumn(self::string('track', 64))
                ->addColumn(self::string('rule_name', 64))
                ->addColumn(self::string('activity', 255))
                ->addColumn(self::string('subject', self::REFERENCE_LENGTH))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::integer('amount'))
                ->addColumn(self::date('occurred_at'))
                ->addColumn(self::string('season', 64))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('track', 'rule_name', 'activity')->create())
                ->addIndex(self::index(self::GAINS.'_subject', 'subject', 'track', 'season'))
                ->addIndex(self::index(self::GAINS.'_member', 'member'))
                ->addIndex(self::index(self::GAINS.'_activity', 'activity'))
                ->addIndex(self::index(self::GAINS.'_occurred', 'occurred_at'))
                ->create());
        }

        if (!$schema->hasTable(self::MILESTONES)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::MILESTONES)
                ->addColumn(self::string('track', 64))
                ->addColumn(self::string('milestone', 64))
                ->addColumn(self::string('subject', self::REFERENCE_LENGTH))
                ->addColumn(self::string('season', 64))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::date('reached_at'))
                ->addColumn(self::string('activity', 255))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('subject', 'track', 'milestone', 'season')->create())
                ->addIndex(self::index(self::MILESTONES.'_member', 'member'))
                ->create());
        }

        if (!$schema->hasTable(self::GESTURES)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::GESTURES)
                ->addColumn(self::string('streak', 64))
                ->addColumn(self::string('activity', 255))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::integer('unit'))
                ->addColumn(self::date('occurred_at'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('streak', 'activity')->create())
                ->addIndex(self::index(self::GESTURES.'_member', 'member', 'streak', 'unit'))
                ->addIndex(self::index(self::GESTURES.'_activity', 'activity'))
                ->addIndex(self::index(self::GESTURES.'_occurred', 'occurred_at'))
                ->create());
        }

        if (!$schema->hasTable(self::FREEZES)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::FREEZES)
                ->addColumn(self::string('streak', 64))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::integer('unit'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('member', 'streak', 'unit')->create())
                ->create());
        }

        if (!$schema->hasTable(self::STATES)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::STATES)
                ->addColumn(self::string('streak', 64))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::integer('current_run'))
                ->addColumn(self::integer('best_run'))
                ->addColumn(Column::editor()->setUnquotedName('run_start')->setTypeName(Types::INTEGER)->setNotNull(false)->create())
                ->addColumn(self::integer('last_active'))
                ->addColumn(self::string('timezone', 64))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('member', 'streak')->create())
                ->addIndex(self::index(self::STATES.'_alive', 'streak', 'current_run', 'last_active'))
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
    private static function integer(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::BIGINT)->create();
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
