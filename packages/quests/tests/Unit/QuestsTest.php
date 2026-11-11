<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Quests\Chains;
use Tilttek\Quests\Completion;
use Tilttek\Quests\Contribution;
use Tilttek\Quests\Duel\Duel;
use Tilttek\Quests\Duel\Duels;
use Tilttek\Quests\Duel\DuelStanding;
use Tilttek\Quests\Enrolment;
use Tilttek\Quests\Erasure;
use Tilttek\Quests\Evaluator;
use Tilttek\Quests\Exception\QuestRefused;
use Tilttek\Quests\Exception\UnknownQuest;
use Tilttek\Quests\InMemoryCatalog;
use Tilttek\Quests\Names;
use Tilttek\Quests\Progress\Finish;
use Tilttek\Quests\Progress\ProgressCalculator;
use Tilttek\Quests\Progress\QuestProgress;
use Tilttek\Quests\Progress\StepProgress;
use Tilttek\Quests\Quest;
use Tilttek\Quests\QuestCompleted;
use Tilttek\Quests\QuestProjection;
use Tilttek\Quests\QuestReader;
use Tilttek\Quests\Quests;
use Tilttek\Quests\QuestState;
use Tilttek\Quests\QuestStatus;
use Tilttek\Quests\Reward;
use Tilttek\Quests\Schedule\Dated;
use Tilttek\Quests\Schedule\Occurrence;
use Tilttek\Quests\Schedule\Permanent;
use Tilttek\Quests\Scope;
use Tilttek\Quests\Step;
use Tilttek\Quests\StepMeasure;
use Tilttek\Quests\Store\InMemoryCompletionStore;
use Tilttek\Quests\Store\InMemoryContributionStore;
use Tilttek\Quests\Store\InMemoryDuelStore;
use Tilttek\Quests\Store\InMemoryEnrolmentStore;
use Tilttek\Quests\Tests\Unit\Fixture\Kit;
use Tilttek\Quests\Tests\Unit\Fixture\Yearly;
use Tilttek\Quests\Window;

#[CoversClass(Quest::class)]
#[CoversClass(Step::class)]
#[CoversClass(Scope::class)]
#[CoversClass(StepMeasure::class)]
#[CoversClass(Window::class)]
#[CoversClass(Occurrence::class)]
#[CoversClass(Dated::class)]
#[CoversClass(Permanent::class)]
#[CoversClass(Enrolment::class)]
#[CoversClass(Completion::class)]
#[CoversClass(Contribution::class)]
#[CoversClass(Duel::class)]
#[CoversClass(Duels::class)]
#[CoversClass(DuelStanding::class)]
#[CoversClass(ProgressCalculator::class)]
#[CoversClass(QuestProgress::class)]
#[CoversClass(StepProgress::class)]
#[CoversClass(Finish::class)]
#[CoversClass(InMemoryContributionStore::class)]
#[CoversClass(InMemoryEnrolmentStore::class)]
#[CoversClass(InMemoryCompletionStore::class)]
#[CoversClass(InMemoryDuelStore::class)]
#[CoversClass(QuestReader::class)]
#[CoversClass(InMemoryCatalog::class)]
#[CoversClass(Chains::class)]
#[CoversClass(Names::class)]
#[CoversClass(Reward::class)]
#[CoversClass(QuestCompleted::class)]
#[CoversClass(QuestStatus::class)]
#[CoversClass(Evaluator::class)]
#[CoversClass(Quests::class)]
#[CoversClass(QuestProjection::class)]
#[CoversClass(Erasure::class)]
final class QuestsTest extends TestCase
{
    public function testAMemberAcceptsAQuestOnceAndAbandonsIt(): void
    {
        $kit = Kit::of(['club' => ['admission' => 'accept', 'steps' => [['verbs' => ['game.played']]]]]);
        $member = Kit::ref('member:1');

        self::assertSame($kit->catalog, $kit->quests->catalog());
        $kit->at('2026-11-01 10:00');
        $accepted = $kit->quests->accept($member, 'club');
        self::assertEquals(new Enrolment('club', $member, new DateTimeImmutable('2026-11-01 10:00 UTC')), $accepted);
        $kit->at('2026-11-02 10:00');
        self::assertEquals($accepted, $kit->quests->accept($member, 'club'));

        $abandoned = $kit->quests->abandon($member, 'club');
        self::assertEquals(new DateTimeImmutable('2026-11-02 10:00 UTC'), $abandoned->abandonedAt);
        $kit->at('2026-11-03 10:00');
        self::assertEquals($abandoned, $kit->quests->abandon($member, 'club'));
        self::assertEquals($abandoned, $kit->enrolments->find('club', $member));

        $again = $kit->quests->accept($member, 'club');
        self::assertEquals(new Enrolment('club', $member, new DateTimeImmutable('2026-11-03 10:00 UTC')), $again);
    }

    public function testAQuestFollowedAutomaticallyIsAbandonedWithoutBeingAccepted(): void
    {
        $kit = Kit::of(['open' => ['steps' => [['verbs' => ['game.played']]]]]);
        $member = Kit::ref('member:1');

        $kit->at('2026-11-01 10:00');
        self::assertEquals(new Enrolment('open', $member, new DateTimeImmutable('2026-11-01 10:00 UTC'), null, new DateTimeImmutable('2026-11-01 10:00 UTC')), $kit->quests->abandon($member, 'open'));
    }

    public function testWhatAMemberAsksIsRefusedWhenItCannotBe(): void
    {
        $kit = Kit::of([
            'club' => ['admission' => 'accept', 'steps' => [['verbs' => ['game.played']]]],
            'later' => ['opens' => '2027-01-01', 'steps' => [['verbs' => ['game.played']]]],
            'next' => ['after' => 'club', 'admission' => 'accept', 'steps' => [['verbs' => ['game.played']]]],
            'raid' => ['kind' => 'team', 'steps' => [['verbs' => ['game.played']]]],
            'race' => ['kind' => 'duel', 'steps' => [['verbs' => ['game.played']]]],
        ]);
        $member = Kit::ref('member:1');
        $refusals = [
            'A team quest is joined under a team, and only a team quest; "club" is a personal quest.' => static fn (): Enrolment => $kit->quests->accept($member, 'club', Kit::ref('team:red')),
            'A team quest is joined under a team, and only a team quest; "raid" is a team quest.' => static fn (): Enrolment => $kit->quests->accept($member, 'raid'),
            '"race" is a duel: a member challenges another to it.' => static fn (): Enrolment => $kit->quests->accept($member, 'race'),
            '"race" is a duel: a member challenges another to it. ' => static fn (): Enrolment => $kit->quests->abandon($member, 'race'),
            '"later" is closed.' => static fn (): Enrolment => $kit->quests->accept($member, 'later'),
            '"later" is closed. ' => static fn (): Enrolment => $kit->quests->abandon($member, 'later'),
            '"next" opens once "club" is accomplished.' => static fn (): Enrolment => $kit->quests->accept($member, 'next'),
            'The member does not follow "club".' => static fn (): Enrolment => $kit->quests->abandon($member, 'club'),
        ];

        foreach ($refusals as $message => $ask) {
            try {
                $ask();
                self::fail('Expected a refusal: '.$message);
            } catch (QuestRefused $questRefused) {
                self::assertSame(trim($message), $questRefused->getMessage());
            }
        }

        $this->expectException(UnknownQuest::class);
        $kit->quests->status($member, 'nope');
    }

    public function testAnAccomplishedQuestCanNeitherBeAcceptedNorAbandoned(): void
    {
        $kit = Kit::of(['club' => ['admission' => 'accept', 'steps' => [['verbs' => ['game.played']]]]]);
        $member = Kit::ref('member:1');
        $kit->at('2026-11-01 09:00');
        $kit->quests->accept($member, 'club');
        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');

        foreach ([static fn (): Enrolment => $kit->quests->accept($member, 'club'), static fn (): Enrolment => $kit->quests->abandon($member, 'club')] as $ask) {
            try {
                $ask();
                self::fail('Expected a refusal.');
            } catch (QuestRefused $questRefused) {
                self::assertSame('"club" is accomplished already.', $questRefused->getMessage());
            }
        }
    }

    public function testAMemberJoinsATeamQuestUnderOneTeamAtATime(): void
    {
        $kit = Kit::of(['raid' => ['kind' => 'team', 'steps' => [['verbs' => ['game.played'], 'count' => 2]]]]);
        $member = Kit::ref('member:1');
        $kit->at('2026-11-01 09:00');
        $joined = $kit->quests->accept($member, 'raid', Kit::ref('team:red'));

        self::assertEquals($joined, $kit->quests->accept($member, 'raid', Kit::ref('team:red')));

        try {
            $kit->quests->accept($member, 'raid', Kit::ref('team:blue'));
            self::fail('Expected a refusal.');
        } catch (QuestRefused $questRefused) {
            self::assertSame('The member follows "raid" with another team; they abandon it before joining another.', $questRefused->getMessage());
        }

        $kit->quests->abandon($member, 'raid');
        self::assertEquals(Kit::ref('team:blue'), $kit->quests->accept($member, 'raid', Kit::ref('team:blue'))->team);

        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');
        $kit->record('g2', 'member:1', 'game.played', '2026-11-01 11:00');

        try {
            $kit->quests->accept(Kit::ref('member:2'), 'raid', Kit::ref('team:blue'));
            self::fail('Expected a refusal.');
        } catch (QuestRefused $questRefused) {
            self::assertSame('"raid" is accomplished already.', $questRefused->getMessage());
        }

        self::assertEquals(Kit::ref('team:red'), $kit->quests->accept(Kit::ref('member:2'), 'raid', Kit::ref('team:red'))->team);
    }

    public function testTheStatusOfAQuestFollowsTheMember(): void
    {
        $kit = Kit::of([
            'club' => ['admission' => 'accept', 'steps' => [['code' => 'play', 'verbs' => ['game.played'], 'count' => 2]]],
            'next' => ['after' => 'club', 'steps' => [['verbs' => ['game.won']]]],
            'december' => ['opens' => '2026-12-01', 'closes' => '2027-01-01', 'steps' => [['verbs' => ['game.played']]]],
            'race' => ['kind' => 'duel', 'steps' => [['verbs' => ['game.played']]]],
            'winter' => ['kind' => 'duel', 'opens' => '2026-12-01', 'steps' => [['verbs' => ['game.played']]]],
        ]);
        $member = Kit::ref('member:1');
        $kit->at('2026-11-01 09:00');

        self::assertSame(
            ['club' => QuestState::Available, 'december' => QuestState::Closed, 'next' => QuestState::Locked],
            $this->states($kit->quests->board($member)),
        );
        self::assertSame(QuestState::Available, $kit->quests->status($member, 'race')->state);
        self::assertSame(QuestState::Closed, $kit->quests->status($member, 'winter')->state);
        self::assertSame(0, $kit->quests->status($member, 'race')->progress->done());

        $kit->quests->accept($member, 'club');
        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');

        $status = $kit->quests->status($member, 'club');
        self::assertSame(QuestState::UnderWay, $status->state);
        self::assertSame(1, $status->progress->steps[0]->value);
        self::assertSame('', $status->occurrence?->code);
        self::assertNull($status->completion);
        self::assertNull($status->team);

        $kit->at('2026-11-01 11:00');
        $kit->quests->abandon($member, 'club');
        self::assertSame(QuestState::Abandoned, $kit->quests->status($member, 'club')->state);
        self::assertSame(1, $kit->quests->status($member, 'club')->progress->steps[0]->value);

        $kit->at('2026-11-01 12:00');
        $kit->quests->accept($member, 'club');
        $kit->record('g2', 'member:1', 'game.played', '2026-11-01 12:00');
        $kit->record('g3', 'member:1', 'game.played', '2026-11-01 13:00');

        $status = $kit->quests->status($member, 'club');
        self::assertSame(QuestState::Completed, $status->state);
        self::assertSame(2, $status->progress->steps[0]->value);
        self::assertEquals(new DateTimeImmutable('2026-11-01 13:00 UTC'), $status->completion?->completedAt);
        self::assertSame(QuestState::UnderWay, $kit->quests->status($member, 'next')->state);

        // The progress of a chained quest starts with the quest before it.
        $kit->record('w0', 'member:2', 'game.won', '2026-11-01 14:00');
        self::assertSame(0, $kit->quests->status(Kit::ref('member:2'), 'next')->progress->steps[0]->value);

        // A dated quest accomplished stays so once closed.
        $kit->record('g4', 'member:1', 'game.played', '2026-12-02 10:00');
        $kit->at('2027-02-01 10:00');

        $closed = $kit->quests->status($member, 'december');
        self::assertSame(QuestState::Completed, $closed->state);
        self::assertNull($closed->occurrence);
        self::assertSame(1, $closed->progress->done());
    }

    public function testAQuestThatComesBackCountsEachOccurrenceOnItsOwn(): void
    {
        $kit = new Kit(new InMemoryCatalog([new Quest('yearly', [new Step('play', new Scope(['game.played']), 2)], schedule: new Yearly())]));
        $member = Kit::ref('member:1');
        $kit->record('g1', 'member:1', 'game.played', '2026-03-01 10:00');
        $kit->record('g2', 'member:1', 'game.played', '2026-04-01 10:00');
        $kit->record('g3', 'member:1', 'game.played', '2027-03-01 10:00');
        self::assertSame(QuestState::UnderWay, $kit->quests->status($member, 'yearly')->state);
        self::assertSame(1, $kit->quests->status($member, 'yearly')->progress->steps[0]->value);

        $kit->record('g4', 'member:1', 'game.played', '2027-04-01 10:00');
        $status = $kit->quests->status($member, 'yearly');
        self::assertSame(QuestState::Completed, $status->state);
        self::assertSame('2027', $status->completion?->occurrence);
        self::assertSame(2, $status->progress->steps[0]->value);
        self::assertCount(2, $kit->quests->completions($member));
    }

    public function testTheStatusOfATeamAndACommunityQuestIsTheirSubjects(): void
    {
        $kit = Kit::of([
            'raid' => ['kind' => 'team', 'closes' => '2026-12-01', 'steps' => [['verbs' => ['game.played'], 'count' => 3]]],
            'drive' => ['kind' => 'community', 'steps' => [['verbs' => ['game.played'], 'count' => 5]]],
        ]);
        $kit->at('2026-11-01 09:00');

        $alone = $kit->quests->status(Kit::ref('member:1'), 'raid');
        self::assertSame(QuestState::Available, $alone->state);
        self::assertNull($alone->team);

        $kit->quests->accept(Kit::ref('member:1'), 'raid', Kit::ref('team:red'));
        $kit->quests->accept(Kit::ref('member:2'), 'raid', Kit::ref('team:red'));
        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');
        $kit->record('g2', 'member:2', 'game.played', '2026-11-01 11:00');
        $kit->record('g3', 'member:3', 'game.played', '2026-11-01 12:00');

        $team = $kit->quests->status(Kit::ref('member:1'), 'raid');
        self::assertSame(QuestState::UnderWay, $team->state);
        self::assertEquals(Kit::ref('team:red'), $team->team);
        self::assertSame(2, $team->progress->steps[0]->value);
        self::assertSame(['member:1', 'member:2'], $team->progress->members);
        self::assertSame(3, $kit->quests->status(Kit::ref('member:9'), 'drive')->progress->steps[0]->value);

        $kit->at('2027-01-01 00:00');
        self::assertSame(QuestState::Closed, new Quests($kit->evaluator, $kit->clock)->status(Kit::ref('member:9'), 'raid')->state);
    }

    public function testTheQuestsAccomplishedAreReadExportedAndErased(): void
    {
        $kit = Kit::of([
            'one' => ['steps' => [['verbs' => ['game.played']]]],
            'raid' => ['kind' => 'team', 'steps' => [['verbs' => ['game.played']]]],
            'race' => ['kind' => 'duel', 'steps' => [['verbs' => ['game.won']]]],
        ]);
        $member = Kit::ref('member:1');
        $kit->at('2026-11-01 09:00');
        $kit->quests->accept($member, 'raid', Kit::ref('team:red'));
        $kit->duelService->challenge('d1', 'race', $member, Kit::ref('member:2'));
        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');
        $kit->record('g2', 'member:2', 'game.played', '2026-11-01 11:00');

        self::assertEquals([new Completion('one', '', $member, new DateTimeImmutable('2026-11-01 10:00 UTC'), 'g1')], $kit->quests->completions($member));
        self::assertCount(1, $kit->quests->completions(Kit::ref('team:red')));

        $export = $kit->quests->export($member);
        self::assertCount(2, $export['contributions']);
        self::assertCount(1, $export['enrolments']);
        self::assertCount(1, $export['completions']);
        self::assertCount(1, $export['duels']);

        self::assertEquals(new Erasure(2, 1, 1, 1), $kit->quests->erase($member));
        self::assertSame(['contributions' => [], 'enrolments' => [], 'completions' => [], 'duels' => []], $kit->quests->export($member));
        self::assertCount(1, $kit->quests->completions(Kit::ref('team:red')));
        self::assertCount(1, $kit->quests->export(Kit::ref('member:2'))['completions']);
    }

    /**
     * @param list<QuestStatus> $statuses
     *
     * @return array<string, QuestState>
     */
    private function states(array $statuses): array
    {
        $states = [];

        foreach ($statuses as $status) {
            $states[$status->quest->code] = $status->state;
        }

        return $states;
    }
}
