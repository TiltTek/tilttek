<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Entry;
use Tilttek\Activity\EntryKind;
use Tilttek\Activity\Verb;
use Tilttek\Activity\VerbPattern;
use Tilttek\Quests\Chains;
use Tilttek\Quests\Completion;
use Tilttek\Quests\Contribution;
use Tilttek\Quests\Duel\Duel;
use Tilttek\Quests\Duel\Duels;
use Tilttek\Quests\Duel\DuelStanding;
use Tilttek\Quests\Duel\DuelStatus;
use Tilttek\Quests\Enrolment;
use Tilttek\Quests\Erasure;
use Tilttek\Quests\Evaluator;
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
final class QuestProjectionTest extends TestCase
{
    public function testItIsNamedAndReadsTheVerbsOfItsQuests(): void
    {
        $kit = Kit::of([
            'a' => ['steps' => [['verbs' => ['game.played', 'game.*']], ['verbs' => ['book.read']]]],
            'b' => ['steps' => [['verbs' => ['game.played']]]],
        ]);

        self::assertSame('quests', $kit->projection->name());
        self::assertSame('other', new QuestProjection($kit->evaluator, null, 'other')->name());
        self::assertSame(['game.played', 'game.*', 'book.read'], array_map(static fn (VerbPattern $pattern): string => $pattern->value, $kit->projection->filter()->verbs->patterns));
        self::assertTrue($kit->projection->filter()->verbs->allows(Verb::fromString('game.won')));
        self::assertFalse($kit->projection->filter()->verbs->allows(Verb::fromString('news.read')));
    }

    public function testAPersonalQuestIsAccomplishedWhenItsStepsAreDone(): void
    {
        $kit = Kit::of(['starter' => [
            'steps' => [
                ['code' => 'play', 'verbs' => ['game.played'], 'count' => 2],
                ['code' => 'read', 'verbs' => ['book.read'], 'total' => 100, 'context' => ['system' => 'dnd5e']],
            ],
            'rewards' => [['kind' => 'points', 'code' => 'xp', 'amount' => 50]],
        ]]);

        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');
        $kit->record('r1', 'member:1', 'book.read', '2026-11-01 11:00', 80, ['system' => 'dnd5e']);
        $kit->record('r2', 'member:1', 'book.read', '2026-11-01 12:00', 500, ['system' => 'pf2e']);
        $kit->record('g2', 'member:1', 'game.played', '2026-11-02 10:00');
        $kit->record('n1', 'member:1', 'news.read', '2026-11-02 10:30');
        self::assertSame([], $kit->recorder->events());

        $kit->record('r3', 'member:1', 'book.read', '2026-11-03 09:00', 20, ['system' => 'dnd5e']);
        self::assertSame(['completed starter by member:1 for member:1'], $kit->recorder->events());
        self::assertEquals([new Reward('points', 'xp', 50)], $kit->recorder->completed[0]->rewards());
        self::assertEquals(
            new Completion('starter', '', Kit::ref('member:1'), new DateTimeImmutable('2026-11-03 09:00 UTC'), 'r3'),
            $kit->completions->find('starter', '', Kit::ref('member:1')),
        );

        // Accomplished once: more activity tells nothing, and others go their own way.
        $kit->record('r4', 'member:1', 'book.read', '2026-11-04 09:00', 20, ['system' => 'dnd5e']);
        $kit->record('g3', 'member:2', 'game.played', '2026-11-04 10:00');
        self::assertSame([], $kit->recorder->events());
        self::assertCount(5, $kit->contributions->ofMember(Kit::ref('member:1')));
    }

    public function testOrderedStepsCountOnlyOnceTheStepsBeforeAreDone(): void
    {
        $kit = Kit::of(['path' => [
            'ordered' => true,
            'steps' => [
                ['code' => 'one', 'verbs' => ['a.done']],
                ['code' => 'two', 'verbs' => ['b.done'], 'count' => 2],
                ['code' => 'three', 'verbs' => ['c.done']],
            ],
        ]]);

        $kit->record('c1', 'member:1', 'c.done', '2026-11-01 08:00');
        $kit->record('b1', 'member:1', 'b.done', '2026-11-01 09:00');
        $kit->record('a1', 'member:1', 'a.done', '2026-11-01 10:00');
        $kit->record('b2', 'member:1', 'b.done', '2026-11-01 11:00');
        $kit->record('c2', 'member:1', 'c.done', '2026-11-01 12:00');
        self::assertSame([], $kit->recorder->events());

        $kit->record('b3', 'member:1', 'b.done', '2026-11-01 13:00');
        self::assertSame([], $kit->recorder->events());

        $kit->record('c3', 'member:1', 'c.done', '2026-11-01 14:00');
        self::assertSame(['completed path by member:1 for member:1'], $kit->recorder->events());
    }

    public function testADatedQuestCountsOnlyWhileItIsOpen(): void
    {
        $kit = Kit::of(['december' => ['steps' => [['verbs' => ['game.played'], 'count' => 2]], 'opens' => '2026-12-01', 'closes' => '2027-01-01']]);

        $kit->record('g1', 'member:1', 'game.played', '2026-11-30 23:59');
        $kit->record('g2', 'member:1', 'game.played', '2026-12-01 00:00');
        self::assertSame([], $kit->recorder->events());
        self::assertCount(1, $kit->contributions->ofMember(Kit::ref('member:1')));

        $kit->record('g3', 'member:1', 'game.played', '2027-01-01 00:00');
        self::assertSame([], $kit->recorder->events());

        $kit->record('g4', 'member:2', 'game.played', '2026-12-01 10:00');
        $kit->record('g5', 'member:2', 'game.played', '2026-12-31 23:59');
        self::assertSame(['completed december by member:2 for member:2'], $kit->recorder->events());
    }

    public function testAQuestToAcceptCountsOnlyTheActivityAfterTheAcceptanceAndBeforeTheAbandonment(): void
    {
        $kit = Kit::of(['club' => ['admission' => 'accept', 'steps' => [['verbs' => ['game.played'], 'count' => 2]]]]);

        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');
        self::assertSame([], $kit->contributions->ofMember(Kit::ref('member:1')));

        $kit->at('2026-11-02 10:00');
        $kit->quests->accept(Kit::ref('member:1'), 'club');
        $kit->record('g2', 'member:1', 'game.played', '2026-11-02 10:00');
        $kit->at('2026-11-03 10:00');
        $kit->quests->abandon(Kit::ref('member:1'), 'club');
        $kit->record('g3', 'member:1', 'game.played', '2026-11-03 10:00');
        self::assertCount(1, $kit->contributions->ofMember(Kit::ref('member:1')));

        // Accepted again, the quest counts from the new acceptance.
        $kit->at('2026-11-04 10:00');
        $kit->quests->accept(Kit::ref('member:1'), 'club');
        $kit->record('g4', 'member:1', 'game.played', '2026-11-04 10:00');
        self::assertSame([], $kit->recorder->events());
        $kit->record('g5', 'member:1', 'game.played', '2026-11-04 11:00');
        self::assertSame(['completed club by member:1 for member:1'], $kit->recorder->events());
    }

    public function testAQuestFollowedAutomaticallyStopsCountingOnceAbandoned(): void
    {
        $kit = Kit::of(['open' => ['steps' => [['verbs' => ['game.played'], 'count' => 2]]]]);

        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');
        $kit->at('2026-11-02 10:00');
        $kit->quests->abandon(Kit::ref('member:1'), 'open');
        $kit->record('g2', 'member:1', 'game.played', '2026-11-02 10:00');
        self::assertSame([], $kit->recorder->events());

        // Taken up again, everything counts once more.
        $kit->at('2026-11-03 10:00');
        $kit->quests->accept(Kit::ref('member:1'), 'open');
        $kit->record('g3', 'member:1', 'game.played', '2026-11-03 10:00');
        self::assertSame(['completed open by member:1 for member:1'], $kit->recorder->events());
    }

    public function testAccomplishingAQuestOpensTheNext(): void
    {
        $kit = Kit::of([
            'first' => ['steps' => [['verbs' => ['game.played']]]],
            'second' => ['after' => 'first', 'steps' => [['verbs' => ['game.played'], 'count' => 2]]],
            'third' => ['after' => 'second', 'steps' => [['verbs' => ['game.won']]]],
        ]);

        $kit->record('w0', 'member:1', 'game.won', '2026-11-01 09:00');
        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');
        self::assertSame(['completed first by member:1 for member:1'], $kit->recorder->events());

        $kit->record('g2', 'member:1', 'game.played', '2026-11-01 11:00');
        self::assertSame(['completed second by member:1 for member:1'], $kit->recorder->events());

        $kit->record('w1', 'member:1', 'game.won', '2026-11-01 12:00');
        self::assertSame(['completed third by member:1 for member:1'], $kit->recorder->events());
    }

    public function testAChainedQuestListedBeforeItsPredecessorIsAccomplishedByTheSameActivity(): void
    {
        $kit = Kit::of([
            'a-next' => ['after' => 'b-first', 'steps' => [['verbs' => ['game.played']]]],
            'b-first' => ['steps' => [['verbs' => ['game.played']]]],
            'c-later' => ['after' => 'b-first', 'opens' => '2027-01-01', 'steps' => [['verbs' => ['game.played']]]],
        ]);

        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');

        self::assertSame(['completed b-first by member:1 for member:1', 'completed a-next by member:1 for member:1'], $kit->recorder->events());
    }

    public function testACommunityChallengeIsAccomplishedOnceForEveryoneWhoTookPart(): void
    {
        $kit = Kit::of(['thousand' => ['kind' => 'community', 'steps' => [['verbs' => ['game.played'], 'total' => 10]]]]);

        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00', 4);
        $kit->record('g2', 'member:2', 'game.played', '2026-11-01 11:00', 3);
        $kit->at('2026-11-01 11:30');
        $kit->quests->abandon(Kit::ref('member:3'), 'thousand');
        $kit->record('g3', 'member:3', 'game.played', '2026-11-01 12:00', 5);
        self::assertSame([], $kit->recorder->events());

        $kit->record('g4', 'member:4', 'game.played', '2026-11-01 13:00', 3);
        self::assertSame(['completed thousand by community:thousand for member:1,member:2,member:4'], $kit->recorder->events());
        self::assertNotNull($kit->completions->find('thousand', '', Completion::community('thousand')));
    }

    public function testACommunityChallengeToAcceptCountsOnlyThoseWhoAcceptedIt(): void
    {
        $kit = Kit::of(['drive' => ['kind' => 'community', 'admission' => 'accept', 'steps' => [['verbs' => ['game.played'], 'count' => 2]]]]);

        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');
        $kit->at('2026-11-01 10:30');
        $kit->quests->accept(Kit::ref('member:2'), 'drive');
        $kit->record('g2', 'member:2', 'game.played', '2026-11-01 11:00');
        self::assertSame([], $kit->recorder->events());

        $kit->at('2026-11-01 11:30');
        $kit->quests->accept(Kit::ref('member:3'), 'drive');
        $kit->record('g3', 'member:3', 'game.played', '2026-11-01 12:00');
        self::assertSame(['completed drive by community:drive for member:2,member:3'], $kit->recorder->events());
    }

    public function testATeamQuestAddsUpItsMembersAndRewardsThoseWhoStillFollowIt(): void
    {
        $kit = Kit::of(['raid' => ['kind' => 'team', 'steps' => [['verbs' => ['game.played'], 'count' => 3]]]]);
        $red = Kit::ref('team:red');
        $blue = Kit::ref('team:blue');

        $kit->at('2026-11-01 09:00');
        $kit->quests->accept(Kit::ref('member:1'), 'raid', $red);
        $kit->quests->accept(Kit::ref('member:2'), 'raid', $red);
        $kit->quests->accept(Kit::ref('member:3'), 'raid', $red);
        $kit->quests->accept(Kit::ref('member:4'), 'raid', $blue);
        $kit->record('g0', 'member:9', 'game.played', '2026-11-01 09:30');
        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');
        $kit->record('g2', 'member:4', 'game.played', '2026-11-01 10:30');
        $kit->record('g3', 'member:3', 'game.played', '2026-11-01 11:00');
        $kit->at('2026-11-01 11:30');
        $kit->quests->abandon(Kit::ref('member:3'), 'raid');
        $kit->record('g4', 'member:3', 'game.played', '2026-11-01 11:45');
        self::assertSame([], $kit->recorder->events());

        $kit->record('g5', 'member:2', 'game.played', '2026-11-01 12:00');
        self::assertSame(['completed raid by team:red for member:1,member:2'], $kit->recorder->events());
        self::assertNull($kit->completions->find('raid', '', $blue));
    }

    public function testTheRewardsOfATeamGoToItsMembersWhoStillFollowIt(): void
    {
        $kit = Kit::of(['raid' => ['kind' => 'team', 'steps' => [['verbs' => ['game.played'], 'count' => 2]]]]);
        $kit->at('2026-11-01 09:00');

        foreach (['member:1', 'member:2', 'member:3'] as $member) {
            $kit->quests->accept(Kit::ref($member), 'raid', Kit::ref('team:red'));
        }

        $kit->quests->abandon(Kit::ref('member:2'), 'raid');
        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');
        $kit->record('g2', 'member:3', 'game.played', '2026-11-01 11:00');

        self::assertEquals([Kit::ref('member:1'), Kit::ref('member:3')], $kit->recorder->completed[0]->beneficiaries);
    }

    public function testTheFirstSideToAccomplishADuelWinsIt(): void
    {
        $kit = Kit::of(['race' => ['kind' => 'duel', 'steps' => [['verbs' => ['game.played'], 'count' => 2]], 'duel' => ['length' => 'P2D']]]);
        $one = Kit::ref('member:1');
        $two = Kit::ref('member:2');

        $kit->at('2026-11-01 09:00');
        $kit->duelService->challenge('d1', 'race', $one, $two);
        $kit->record('g0', 'member:1', 'game.played', '2026-11-01 09:30');
        $kit->at('2026-11-01 10:00');
        $kit->duelService->accept('d1', $two);
        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');
        $kit->record('g2', 'member:2', 'game.played', '2026-11-01 11:00');
        $kit->record('g3', 'member:3', 'game.played', '2026-11-01 11:30');
        self::assertSame([], $kit->recorder->events());

        $kit->record('g4', 'member:2', 'game.played', '2026-11-01 12:00');
        self::assertSame(['completed race by member:2 for member:2', 'duel d1 won by member:2'], $kit->recorder->events());
        $duel = $kit->duels->find('d1');
        self::assertSame(DuelStatus::Won, $duel?->status);
        self::assertEquals(new DateTimeImmutable('2026-11-01 12:00 UTC'), $duel->decidedAt);
        self::assertEquals(new Completion('race', 'd1', $two, new DateTimeImmutable('2026-11-01 12:00 UTC'), 'g4'), $kit->completions->find('race', 'd1', $two));

        // Decided: the other side's activity no longer counts.
        $kit->record('g5', 'member:1', 'game.played', '2026-11-01 13:00');
        self::assertSame([], $kit->recorder->events());
    }

    public function testADuelCountsOnlyUntilItEnds(): void
    {
        $kit = Kit::of(['race' => ['kind' => 'duel', 'steps' => [['verbs' => ['game.played'], 'count' => 2]], 'duel' => ['length' => 'PT2H']]]);

        $kit->at('2026-11-01 10:00');
        $kit->duelService->challenge('d1', 'race', Kit::ref('member:1'), Kit::ref('member:2'));
        $kit->duelService->accept('d1', Kit::ref('member:2'));
        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 11:00');
        $kit->record('g2', 'member:1', 'game.played', '2026-11-01 12:00');

        self::assertSame([], $kit->recorder->events());
        self::assertCount(1, $kit->contributions->ofMember(Kit::ref('member:1')));
    }

    public function testACancellationTakesBackItsContributionsAndLeavesAnAccomplishedQuestAccomplished(): void
    {
        $kit = Kit::of([
            'two' => ['steps' => [['verbs' => ['game.played'], 'count' => 2]]],
            'three' => ['steps' => [['verbs' => ['game.played'], 'count' => 3]]],
        ]);

        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');
        $kit->record('g2', 'member:1', 'game.played', '2026-11-01 11:00');
        self::assertSame(['completed two by member:1 for member:1'], $kit->recorder->events());

        $kit->cancel('g2', '2026-11-01 12:00');
        self::assertCount(2, $kit->contributions->ofMember(Kit::ref('member:1')));
        self::assertNotNull($kit->completions->find('two', '', Kit::ref('member:1')));

        $kit->record('g3', 'member:1', 'game.played', '2026-11-01 13:00');
        self::assertSame([], $kit->recorder->events());
    }

    public function testARebuildReplaysTheActivityWithoutTellingTheQuestsAccomplishedAgain(): void
    {
        $kit = Kit::of([
            'two' => ['steps' => [['verbs' => ['game.played'], 'count' => 2]]],
            'race' => ['kind' => 'duel', 'steps' => [['verbs' => ['game.won']]]],
        ]);

        $kit->at('2026-11-01 09:00');
        $kit->duelService->challenge('d1', 'race', Kit::ref('member:1'), Kit::ref('member:2'));
        $kit->duelService->accept('d1', Kit::ref('member:2'));
        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');
        $kit->record('g2', 'member:1', 'game.played', '2026-11-01 11:00');
        $kit->record('w1', 'member:2', 'game.won', '2026-11-01 11:30');
        $kit->record('g3', 'member:2', 'game.played', '2026-11-02 10:00');
        self::assertSame(['completed two by member:1 for member:1', 'completed race by member:2 for member:2', 'duel d1 won by member:2'], $kit->recorder->events());

        $kit->projector->rebuild($kit->projection);
        self::assertSame([], $kit->recorder->events());
        self::assertNotNull($kit->completions->find('two', '', Kit::ref('member:1')));
        self::assertSame(DuelStatus::Won, $kit->duels->find('d1')?->status);

        // A rebuild from a moment forgets and replays only from there; what it accomplishes for the first time is told.
        $kit->record('g4', 'member:2', 'game.played', '2026-11-02 11:00');
        self::assertSame(['completed two by member:2 for member:2'], $kit->recorder->events());
        $kit->projector->rebuild($kit->projection, new DateTimeImmutable('2026-11-02 00:00 UTC'));
        self::assertSame([], $kit->recorder->events());
        self::assertCount(1, $kit->completions->ofSubject(Kit::ref('member:1')));
        self::assertCount(2, $kit->completions->ofSubject(Kit::ref('member:2')));

        // With the journal of accomplished quests emptied, the next accomplishment is told.
        $kit->completions->forget(null);
        $kit->projector->rebuild($kit->projection, new DateTimeImmutable('2026-11-03 00:00 UTC'));
        $kit->record('g5', 'member:1', 'game.played', '2026-11-03 10:00');
        self::assertSame(['completed two by member:1 for member:1'], $kit->recorder->events());
    }

    public function testARebuildUnderAChangedRuleForgetsWhatTheOldRuleCounted(): void
    {
        $kit = Kit::of(['two' => ['steps' => [['verbs' => ['game.played'], 'count' => 2]]]]);
        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');
        $kit->record('w1', 'member:1', 'game.won', '2026-11-01 11:00');

        $evaluator = new Evaluator(InMemoryCatalog::fromArray(['two' => ['steps' => [['verbs' => ['game.won'], 'count' => 2]]]]), $kit->contributions, $kit->enrolments, $kit->completions, $kit->duels);
        $kit->projector->rebuild(new QuestProjection($evaluator));

        self::assertSame(['w1'], array_map(static fn (Contribution $contribution): string => $contribution->activity, $kit->contributions->of('two')));
    }

    public function testARebuildTellsNoneOfTheQuestsASubjectAccomplishesAgain(): void
    {
        $kit = Kit::of([
            'one' => ['steps' => [['verbs' => ['game.played']]]],
            'two' => ['steps' => [['verbs' => ['game.played'], 'count' => 2]]],
        ]);
        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');
        $kit->record('g2', 'member:1', 'game.played', '2026-11-01 11:00');
        self::assertCount(2, $kit->recorder->events());

        $kit->projector->rebuild($kit->projection);

        self::assertSame([], $kit->recorder->events());
    }

    public function testADuelIsDecidedOnlyByTheDuelsTheActivityFallsIn(): void
    {
        $kit = Kit::of(['race' => ['kind' => 'duel', 'steps' => [['verbs' => ['game.played']]], 'duel' => ['length' => 'PT1H']]]);
        $kit->at('2026-11-01 09:00');
        $kit->duelService->challenge('d1', 'race', Kit::ref('member:1'), Kit::ref('member:2'));
        $kit->duelService->accept('d1', Kit::ref('member:2'));
        $kit->at('2026-11-01 11:00');
        $kit->duelService->challenge('d2', 'race', Kit::ref('member:1'), Kit::ref('member:3'));
        $kit->duelService->accept('d2', Kit::ref('member:3'));

        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 11:30');

        self::assertSame(['completed race by member:1 for member:1', 'duel d2 won by member:1'], $kit->recorder->events());
        self::assertSame(DuelStatus::Accepted, $kit->duels->find('d1')?->status);
    }

    public function testAProjectionWithoutObserverAccomplishesQuestsAndDecidesDuels(): void
    {
        $catalog = InMemoryCatalog::fromArray([
            'one' => ['steps' => [['verbs' => ['game.played']]]],
            'race' => ['kind' => 'duel', 'steps' => [['verbs' => ['game.played']]]],
        ]);
        $kit = new Kit($catalog);
        $evaluator = new Evaluator($catalog, $kit->contributions, $kit->enrolments, $kit->completions, $kit->duels);
        $kit->at('2026-11-01 09:00');
        $kit->duelService->challenge('d1', 'race', Kit::ref('member:1'), Kit::ref('member:2'));
        $kit->duelService->accept('d1', Kit::ref('member:2'));

        $at = new DateTimeImmutable('2026-11-01 10:00 UTC');

        new QuestProjection($evaluator)->apply(new Entry(1, EntryKind::Recorded, Activity::of('g1', 'member:1', 'game.played', $at), $at));

        self::assertNotNull($kit->completions->find('one', '', Kit::ref('member:1')));
        self::assertSame(DuelStatus::Won, $kit->duels->find('d1')?->status);
    }

    public function testAnEntryHandedTwiceCountsOnce(): void
    {
        $catalog = InMemoryCatalog::fromArray(['two' => ['steps' => [['verbs' => ['game.played'], 'count' => 2]]]]);
        $evaluator = new Evaluator($catalog, new InMemoryContributionStore(), new InMemoryEnrolmentStore(), new InMemoryCompletionStore(), new InMemoryDuelStore());
        $projection = new QuestProjection($evaluator);
        $entry = new Entry(1, EntryKind::Recorded, Activity::of('g1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-01 10:00 UTC')), new DateTimeImmutable('2026-11-01 10:00 UTC'));

        $projection->apply($entry);
        $projection->apply($entry);

        self::assertNull($evaluator->completions->find('two', '', Kit::ref('member:1')));
        self::assertCount(1, $evaluator->contributions->of('two'));
    }

    public function testClearingTheReplayTellsAQuestAccomplishedAfterIt(): void
    {
        $kit = Kit::of(['one' => ['steps' => [['verbs' => ['game.played']]]]]);
        $kit->record('g1', 'member:1', 'game.played', '2026-11-01 10:00');
        self::assertSame(['completed one by member:1 for member:1'], $kit->recorder->events());

        // Under the new rule, playing no longer accomplishes the quest: the rebuild forgets it.
        $evaluator = new Evaluator(InMemoryCatalog::fromArray(['one' => ['steps' => [['verbs' => ['game.won']]]]]), $kit->contributions, $kit->enrolments, $kit->completions, $kit->duels);
        $projection = new QuestProjection($evaluator, $kit->recorder);
        $kit->projector->rebuild($projection);

        $projection->clearReplay();
        self::assertEquals(new QuestProjection($evaluator, $kit->recorder), $projection);

        $kit->log->append(Activity::of('w1', 'member:1', 'game.won', new DateTimeImmutable('2026-11-02 10:00 UTC')), new DateTimeImmutable('2026-11-02 10:00 UTC'));
        $kit->projector->catchUp($projection);
        self::assertSame(['completed one by member:1 for member:1'], $kit->recorder->events());
    }
}
