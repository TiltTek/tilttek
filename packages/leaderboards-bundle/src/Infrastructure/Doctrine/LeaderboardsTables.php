<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The nine tables of the leaderboards, added to the schema Doctrine generates so that the application's migrations
 * create them:
 *
 * - `tilttek_leaderboards_contribution`: what each activity brought to each board and its tie-breaks, one row per board
 *   and activity — the primary key is what makes an activity count once, even when two processes write it;
 * - `tilttek_leaderboards_score`: the score and the tie-breaks of each member on each board and period, indexed in rank
 *   order;
 * - `tilttek_leaderboards_snapshot`: the boards frozen at the end of a period, one row per board and period;
 * - `tilttek_leaderboards_placing`: the members' lines in each snapshot;
 * - `tilttek_leaderboards_appearance`: how each member who chose to appear does, by name or pseudonym;
 * - `tilttek_leaderboards_league_tier`: the tier each member reached on each board with a league;
 * - `tilttek_leaderboards_league_membership`: each member's division and cohort of each week they took part in, and
 *   where they went at its end — the primary key places a member once a week, even when two processes place them;
 * - `tilttek_leaderboards_league_week`: the weeks settled, one row each — the primary key is what settles a week once;
 * - `tilttek_leaderboards_lead`: the leads taken as scores changed, one row per board, period and member — the primary
 *   key is what tells a member once that they took the lead.
 */
final class LeaderboardsTables
{
    public const string CONTRIBUTIONS = 'tilttek_leaderboards_contribution';

    public const string SCORES = 'tilttek_leaderboards_score';

    public const string SNAPSHOTS = 'tilttek_leaderboards_snapshot';

    public const string PLACINGS = 'tilttek_leaderboards_placing';

    public const string APPEARANCES = 'tilttek_leaderboards_appearance';

    public const string LEAGUE_TIERS = 'tilttek_leaderboards_league_tier';

    public const string LEAGUE_MEMBERSHIPS = 'tilttek_leaderboards_league_membership';

    public const string LEAGUE_WEEKS = 'tilttek_leaderboards_league_week';

    public const string LEADS = 'tilttek_leaderboards_lead';

    /**
     * A resource reference: a kind of 64 bytes, a colon and an identifier of 255.
     */
    public const int REFERENCE_LENGTH = 320;

    /**
     * The key a board's scores are kept under: a name of 64 bytes, or — on a board ranked by object — the name, "@"
     * and a digest of 24.
     */
    public const int BOARD_KEY_LENGTH = 96;

    private function __construct()
    {
    }

    /**
     * The schema with the nine tables added, those it already has left as they are.
     */
    public static function configure(Schema $schema): Schema
    {
        $editor = $schema->edit();

        if (!$schema->hasTable(self::CONTRIBUTIONS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::CONTRIBUTIONS)
                ->addColumn(self::string('board', self::BOARD_KEY_LENGTH))
                ->addColumn(self::string('activity', 255))
                ->addColumn(self::string('period', 64))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::integer('amount'))
                ->addColumn(self::date('occurred_at'))
                ->addColumn(self::small('tie_breaks'))
                ->addColumn(self::integer('tie_break_1'))
                ->addColumn(self::integer('tie_break_2'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('board', 'activity')->create())
                ->addIndex(self::index(self::CONTRIBUTIONS.'_score', 'board', 'period', 'member'))
                ->addIndex(self::index(self::CONTRIBUTIONS.'_activity', 'activity'))
                ->addIndex(self::index(self::CONTRIBUTIONS.'_occurred', 'occurred_at'))
                ->addIndex(self::index(self::CONTRIBUTIONS.'_member', 'member'))
                ->create());
        }

        if (!$schema->hasTable(self::SCORES)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::SCORES)
                ->addColumn(self::string('board', self::BOARD_KEY_LENGTH))
                ->addColumn(self::string('period', 64))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::integer('score'))
                ->addColumn(self::date('reached_at'))
                ->addColumn(self::small('tie_breaks'))
                ->addColumn(self::integer('tie_break_1'))
                ->addColumn(self::integer('tie_break_2'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('board', 'period', 'member')->create())
                ->addIndex(self::index(self::SCORES.'_rank', 'board', 'period', 'score', 'tie_break_1', 'tie_break_2', 'reached_at'))
                ->addIndex(self::index(self::SCORES.'_member', 'member'))
                ->create());
        }

        if (!$schema->hasTable(self::SNAPSHOTS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::SNAPSHOTS)
                ->addColumn(self::string('board', self::BOARD_KEY_LENGTH))
                ->addColumn(self::string('period', 64))
                ->addColumn(self::date('taken_at'))
                ->addColumn(self::integer('participants'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('board', 'period')->create())
                ->create());
        }

        if (!$schema->hasTable(self::PLACINGS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::PLACINGS)
                ->addColumn(self::string('board', self::BOARD_KEY_LENGTH))
                ->addColumn(self::string('period', 64))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::integer('placing'))
                ->addColumn(self::integer('score'))
                ->addColumn(self::date('reached_at'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('board', 'period', 'member')->create())
                ->addIndex(self::index(self::PLACINGS.'_member', 'member'))
                ->create());
        }

        if (!$schema->hasTable(self::APPEARANCES)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::APPEARANCES)
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::string('visibility', 16))
                ->addColumn(Column::editor()->setUnquotedName('pseudonym')->setTypeName(Types::STRING)->setLength(160)->setNotNull(false)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('member')->create())
                ->create());
        }

        if (!$schema->hasTable(self::LEAGUE_TIERS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::LEAGUE_TIERS)
                ->addColumn(self::string('board', 64))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::string('tier', 64))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('board', 'member')->create())
                ->addIndex(self::index(self::LEAGUE_TIERS.'_member', 'member'))
                ->create());
        }

        if (!$schema->hasTable(self::LEAGUE_MEMBERSHIPS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::LEAGUE_MEMBERSHIPS)
                ->addColumn(self::string('board', 64))
                ->addColumn(self::string('week', 64))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::string('tier', 64))
                ->addColumn(self::integer('division'))
                ->addColumn(self::small('cohort'))
                ->addColumn(self::date('joined_at'))
                ->addColumn(Column::editor()->setUnquotedName('movement')->setTypeName(Types::STRING)->setLength(16)->setNotNull(false)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('board', 'week', 'member')->create())
                ->addIndex(self::index(self::LEAGUE_MEMBERSHIPS.'_division', 'board', 'week', 'tier', 'division'))
                ->addIndex(self::index(self::LEAGUE_MEMBERSHIPS.'_member', 'member'))
                ->create());
        }

        if (!$schema->hasTable(self::LEAGUE_WEEKS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::LEAGUE_WEEKS)
                ->addColumn(self::string('board', 64))
                ->addColumn(self::string('week', 64))
                ->addColumn(self::date('settled_at'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('board', 'week')->create())
                ->create());
        }

        if (!$schema->hasTable(self::LEADS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::LEADS)
                ->addColumn(self::string('board', self::BOARD_KEY_LENGTH))
                ->addColumn(self::string('period', 64))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::date('taken_at'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('board', 'period', 'member')->create())
                ->addIndex(self::index(self::LEADS.'_member', 'member'))
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
    private static function small(string $name): Column
    {
        return Column::editor()->setUnquotedName($name)->setTypeName(Types::SMALLINT)->create();
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
