<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Schema\Column;
use Doctrine\DBAL\Schema\Index;
use Doctrine\DBAL\Schema\PrimaryKeyConstraint;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\Table;
use Doctrine\DBAL\Types\Types;

/**
 * The five tables of the quests, added to the schema Doctrine generates so that the application's migrations create
 * them:
 *
 * - `tilttek_quests_contribution`: what each activity brought to a step, one row per quest, step and activity — the
 *   primary key is what makes an activity count once, even when two processes write it;
 * - `tilttek_quests_enrolment`: the members' acceptances and abandonments, one row per quest and member;
 * - `tilttek_quests_completion`: the journal of accomplished quests, one row per quest, occurrence and subject;
 * - `tilttek_quests_duel`: the duels, by key;
 * - `tilttek_quests_definition`: the quests themselves, for a catalog kept in the database, each definition as JSON.
 */
final class QuestsTables
{
    public const string CONTRIBUTIONS = 'tilttek_quests_contribution';

    public const string ENROLMENTS = 'tilttek_quests_enrolment';

    public const string COMPLETIONS = 'tilttek_quests_completion';

    public const string DUELS = 'tilttek_quests_duel';

    public const string DEFINITIONS = 'tilttek_quests_definition';

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

        if (!$schema->hasTable(self::CONTRIBUTIONS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::CONTRIBUTIONS)
                ->addColumn(self::string('quest', 64))
                ->addColumn(self::string('step', 64))
                ->addColumn(self::string('activity', 255))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::integer('value'))
                ->addColumn(self::date('occurred_at'))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('quest', 'step', 'activity')->create())
                ->addIndex(self::index(self::CONTRIBUTIONS.'_quest', 'quest', 'occurred_at'))
                ->addIndex(self::index(self::CONTRIBUTIONS.'_member', 'member'))
                ->addIndex(self::index(self::CONTRIBUTIONS.'_activity', 'activity'))
                ->addIndex(self::index(self::CONTRIBUTIONS.'_occurred', 'occurred_at'))
                ->create());
        }

        if (!$schema->hasTable(self::ENROLMENTS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::ENROLMENTS)
                ->addColumn(self::string('quest', 64))
                ->addColumn(self::string('member', self::REFERENCE_LENGTH))
                ->addColumn(self::date('accepted_at'))
                ->addColumn(self::nullable(self::string('team', self::REFERENCE_LENGTH)))
                ->addColumn(self::nullable(self::date('abandoned_at')))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('quest', 'member')->create())
                ->addIndex(self::index(self::ENROLMENTS.'_member', 'member'))
                ->create());
        }

        if (!$schema->hasTable(self::COMPLETIONS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::COMPLETIONS)
                ->addColumn(self::string('quest', 64))
                ->addColumn(self::string('occurrence', 200))
                ->addColumn(self::string('subject', self::REFERENCE_LENGTH))
                ->addColumn(self::date('completed_at'))
                ->addColumn(self::string('activity', 255))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('quest', 'occurrence', 'subject')->create())
                ->addIndex(self::index(self::COMPLETIONS.'_subject', 'subject', 'quest'))
                ->addIndex(self::index(self::COMPLETIONS.'_completed', 'completed_at'))
                ->create());
        }

        if (!$schema->hasTable(self::DUELS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::DUELS)
                ->addColumn(self::string('duel_key', 200))
                ->addColumn(self::string('quest', 64))
                ->addColumn(self::string('challenger', self::REFERENCE_LENGTH))
                ->addColumn(self::string('opponent', self::REFERENCE_LENGTH))
                ->addColumn(self::date('offered_at'))
                ->addColumn(self::string('status', 16))
                ->addColumn(self::nullable(self::date('accepted_at')))
                ->addColumn(self::nullable(self::date('ends_at')))
                ->addColumn(self::nullable(self::string('winner', self::REFERENCE_LENGTH)))
                ->addColumn(self::nullable(self::date('decided_at')))
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('duel_key')->create())
                ->addIndex(self::index(self::DUELS.'_challenger', 'challenger'))
                ->addIndex(self::index(self::DUELS.'_opponent', 'opponent'))
                ->addIndex(self::index(self::DUELS.'_status', 'status', 'quest'))
                ->create());
        }

        if (!$schema->hasTable(self::DEFINITIONS)) {
            $editor->addTable(Table::editor()
                ->setUnquotedName(self::DEFINITIONS)
                ->addColumn(self::string('code', 64))
                ->addColumn(Column::editor()->setUnquotedName('definition')->setTypeName(Types::TEXT)->create())
                ->addPrimaryKeyConstraint(PrimaryKeyConstraint::editor()->setUnquotedColumnNames('code')->create())
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

    private static function nullable(Column $column): Column
    {
        return $column->edit()->setNotNull(false)->create();
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
