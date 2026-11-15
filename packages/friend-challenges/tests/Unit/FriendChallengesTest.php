<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tilttek\FriendChallenges\Challenge;
use Tilttek\FriendChallenges\ChallengeRules;
use Tilttek\FriendChallenges\ChallengeState;
use Tilttek\FriendChallenges\Event\ChallengeCreated;
use Tilttek\FriendChallenges\Event\ChallengeEnded;
use Tilttek\FriendChallenges\Event\ChallengeFrozen;
use Tilttek\FriendChallenges\Event\ChallengeUnfrozen;
use Tilttek\FriendChallenges\Event\ChallengeWon;
use Tilttek\FriendChallenges\Event\MemberInvited;
use Tilttek\FriendChallenges\Event\ParticipantJoined;
use Tilttek\FriendChallenges\Event\ProofDecided;
use Tilttek\FriendChallenges\Event\ProofSubmitted;
use Tilttek\FriendChallenges\Exception\ChallengeConflict;
use Tilttek\FriendChallenges\Exception\ChallengeRefused;
use Tilttek\FriendChallenges\Exception\InvalidChallenge;
use Tilttek\FriendChallenges\FriendChallenges;
use Tilttek\FriendChallenges\ProofState;
use Tilttek\FriendChallenges\Report;
use Tilttek\FriendChallenges\ReportReason;
use Tilttek\FriendChallenges\Tests\Unit\Fixture\BusyStore;
use Tilttek\FriendChallenges\Tests\Unit\Fixture\FailingEscrow;
use Tilttek\FriendChallenges\Tests\Unit\Fixture\World;
use Tilttek\FriendChallenges\Validation;
use Tilttek\FriendChallenges\Visibility;
use Tilttek\Points\Exception\InsufficientPoints;

#[CoversClass(FriendChallenges::class)]
#[CoversClass(ChallengeCreated::class)]
#[CoversClass(MemberInvited::class)]
#[CoversClass(ParticipantJoined::class)]
#[CoversClass(ProofSubmitted::class)]
#[CoversClass(ProofDecided::class)]
#[CoversClass(ChallengeWon::class)]
#[CoversClass(ChallengeEnded::class)]
#[CoversClass(ChallengeFrozen::class)]
#[CoversClass(ChallengeUnfrozen::class)]
final class FriendChallengesTest extends TestCase
{
    public function testACreatedChallengeHoldsItsPrizeAndInvitesEachFriendOnce(): void
    {
        $w = new World();

        $challenge = $w->challenges->create($w->draft(), World::ana());

        self::assertSame(ChallengeState::Open, $challenge->state);
        self::assertSame(900, $w->points->balance(World::ana()));
        self::assertSame(100, $w->points->held('friend-challenge:c1'));
        self::assertSame([ChallengeCreated::class, MemberInvited::class, MemberInvited::class, MemberInvited::class], array_map(static fn (object $event): string => $event::class, $w->events->events));
        self::assertInstanceOf(ChallengeCreated::class, $w->events->events[0]);
        self::assertSame('c1', $w->events->events[0]->challenge);
        self::assertTrue($w->events->events[0]->creator->equals(World::ana()));
        self::assertInstanceOf(MemberInvited::class, $w->events->events[3]);
        self::assertSame('c1', $w->events->events[3]->challenge);
        self::assertTrue($w->events->events[3]->member->equals(World::dee()));
        self::assertSame($challenge->toArray(), $w->challenges->get('c1')->toArray());
    }

    public function testACreationSentAgainGivesBackTheChallengeAndHoldsNothingMore(): void
    {
        $w = new World();
        $first = $w->challenges->create($w->draft(), World::ana());
        $w->events->take();

        $again = $w->challenges->create($w->draft(), World::ana());

        self::assertSame($first->toArray(), $again->toArray());
        self::assertSame(900, $w->points->balance(World::ana()));
        self::assertSame([], $w->events->take());
    }

    public function testAnIdentifierNamesOneChallenge(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());

        $this->expectException(ChallengeRefused::class);
        $this->expectExceptionMessageIsOrContains('exists already, with other values');

        $w->challenges->create($w->draft(prize: 50), World::ana());
    }

    public function testOnlyFriendsAreInvited(): void
    {
        $w = new World();

        try {
            $w->challenges->create($w->draft(invitees: [World::bea(), World::eve()]), World::ana());
            self::fail('Eve is no friend of Ana.');
        } catch (ChallengeRefused $challengeRefused) {
            self::assertStringContainsString('member:eve is not a friend of member:ana', $challengeRefused->getMessage());
        }

        self::assertNull($w->store->get('c1'));
        self::assertSame(1000, $w->points->balance(World::ana()));
    }

    public function testAPrizeThePointsDoNotCoverCreatesNothing(): void
    {
        $w = new World();

        try {
            $w->challenges->create($w->draft(prize: 1001), World::ana());
            self::fail('Ana holds 1 000 points.');
        } catch (InvalidChallenge) {
            self::fail('The prize is within the rules.');
        } catch (InsufficientPoints) {
            self::assertNull($w->store->get('c1'));
            self::assertSame(1000, $w->points->balance(World::ana()));
            self::assertSame([], $w->events->take());
        }
    }

    public function testADraftOutsideTheRulesIsRefusedBeforeAnyPointMoves(): void
    {
        $w = new World(rules: new ChallengeRules(largestPrize: 50));

        try {
            $w->challenges->create($w->draft(), World::ana());
            self::fail('The prize is over the largest.');
        } catch (InvalidChallenge) {
            self::assertSame(1000, $w->points->balance(World::ana()));
        }
    }

    public function testAnotherWriterCreatingTheSameChallengeFirstGivesItBack(): void
    {
        $store = new BusyStore();
        $w = new World($store);
        $store->addedFirst = Challenge::create($w->draft(), World::ana(), $w->clock->now(), new ChallengeRules());

        $challenge = $w->challenges->create($w->draft(), World::ana());

        self::assertSame($store->get('c1')?->toArray(), $challenge->toArray());
        self::assertSame([], $w->events->take());
    }

    public function testAnotherWriterCreatingAnotherChallengeFirstIsAConflict(): void
    {
        $store = new BusyStore();
        $w = new World($store);
        $store->addedFirst = Challenge::create($w->draft(prize: 10), World::ana(), $w->clock->now(), new ChallengeRules());

        $this->expectException(ChallengeConflict::class);

        $w->challenges->create($w->draft(), World::ana());
    }

    public function testTheCreatorAcceptsAProofAndTheWinnerIsPaid(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());
        $w->challenges->join('c1', World::bea());
        $w->challenges->join('c1', World::cy());
        $w->challenges->decline('c1', World::dee());
        $w->clock->sleep(3600);
        $w->challenges->submitProof('c1', 'p1', World::bea(), 'upload:44');
        $w->events->take();

        $won = $w->challenges->approve('c1', 'p1', World::ana());

        self::assertSame(ChallengeState::Won, $won->state);
        self::assertTrue($won->winner?->equals(World::bea()));
        self::assertSame(100, $w->points->balance(World::bea()));
        self::assertSame(0, $w->points->held('friend-challenge:c1'));
        $events = $w->events->events;
        self::assertCount(2, $events);
        self::assertInstanceOf(ProofDecided::class, $events[0]);
        self::assertSame(['c1', 'p1', ProofState::Accepted], [$events[0]->challenge, $events[0]->proof, $events[0]->state]);
        self::assertInstanceOf(ChallengeWon::class, $events[1]);
        self::assertSame(['c1', 'member:bea', 'p1', 100, 'points'], [$events[1]->challenge, $events[1]->winner->toString(), $events[1]->proof, $events[1]->prize, $events[1]->unit]);
    }

    public function testEachMoveIsAnnouncedOnceAndAMoveMadeAgainChangesNothing(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());
        $w->events->take();

        $joined = $w->challenges->join('c1', World::bea());
        self::assertSame([ParticipantJoined::class], $w->events->take());
        self::assertSame($joined->version, $w->challenges->join('c1', World::bea())->version);
        self::assertSame([], $w->events->take());

        $w->challenges->submitProof('c1', 'p1', World::bea(), 'upload:44');
        self::assertInstanceOf(ProofSubmitted::class, $w->events->events[0]);
        self::assertSame(['c1', 'p1', 'member:bea'], [$w->events->events[0]->challenge, $w->events->events[0]->proof, $w->events->events[0]->participant->toString()]);
        $w->events->take();

        $w->challenges->submitProof('c1', 'p1', World::bea(), 'upload:44');
        $w->challenges->decline('c1', World::dee());
        $w->challenges->decline('c1', World::dee());
        self::assertSame([], $w->events->take());

        $w->challenges->reject('c1', 'p1', World::ana());
        self::assertSame([ProofDecided::class], $w->events->take());
        $w->challenges->reject('c1', 'p1', World::ana());
        self::assertSame([], $w->events->take());
    }

    public function testTheParticipantEventNamesTheMember(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());
        $w->events->take();

        $w->challenges->join('c1', World::cy());

        self::assertInstanceOf(ParticipantJoined::class, $w->events->events[0]);
        self::assertSame(['c1', 'member:cy'], [$w->events->events[0]->challenge, $w->events->events[0]->member->toString()]);
    }

    public function testACancelledChallengeGivesItsPrizeBack(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());
        $w->events->take();

        $cancelled = $w->challenges->cancel('c1', World::ana());

        self::assertSame(ChallengeState::Cancelled, $cancelled->state);
        self::assertSame(1000, $w->points->balance(World::ana()));
        self::assertInstanceOf(ChallengeEnded::class, $w->events->events[0]);
        self::assertSame(['c1', ChallengeState::Cancelled], [$w->events->events[0]->challenge, $w->events->events[0]->state]);
    }

    public function testAChallengeExpiresOnceItsReviewIsOver(): void
    {
        $w = new World();
        $w->challenges->create($w->draft('c1'), World::ana());
        $w->clock->sleep(86_400);
        $w->challenges->create($w->draft('c2'), World::ana());
        $w->challenges->create($w->draft('c3'), World::ana());
        $w->challenges->cancel('c3', World::ana());

        try {
            $w->challenges->expire('c1');
            self::fail('The challenge is still open.');
        } catch (ChallengeRefused) {
            self::assertSame(0, $w->challenges->expireDue());
        }

        $w->clock->sleep(9 * 86_400);
        $w->events->take();

        self::assertSame(1, $w->challenges->expireDue(1));
        self::assertSame(ChallengeState::Expired, $w->challenges->get('c1')->state);
        self::assertSame(ChallengeState::Open, $w->challenges->get('c2')->state);
        self::assertSame([ChallengeEnded::class], $w->events->take());
        self::assertSame(0, $w->challenges->expireDue());
        $w->clock->sleep(86_400);
        self::assertSame(1, $w->challenges->expireDue());
        self::assertSame(1000, $w->points->balance(World::ana()));
        self::assertSame(0, $w->challenges->expireDue());
    }

    public function testExpiringRunsAtLeastOneAndAtMostFiveHundred(): void
    {
        $w = new World();

        foreach (range(1, 3) as $n) {
            $w->challenges->create($w->draft('c'.$n, prize: 1), World::ana());
        }

        $w->clock->sleep(11 * 86_400);

        self::assertSame(1, $w->challenges->expireDue(0));
        self::assertSame(2, $w->challenges->expireDue(FriendChallenges::MAX_BATCH + 10));
    }

    public function testExpiringAsksTheStoreForOneToFiveHundred(): void
    {
        $store = new BusyStore();
        $w = new World($store);

        foreach ([[0, 1], [7, 7], [FriendChallenges::MAX_BATCH, 500], [10_000, 500]] as [$asked, $sent]) {
            $w->challenges->expireDue($asked);
            self::assertSame($sent, $store->limit);
        }

        $w->challenges->expireDue();
        self::assertSame(100, $store->limit);
    }

    public function testExpiringLeavesAChallengeAnotherWriterKeepsBusy(): void
    {
        $store = new BusyStore();
        $w = new World($store);
        $w->challenges->create($w->draft(), World::ana());
        $w->clock->sleep(11 * 86_400);

        $store->failures = FriendChallenges::ATTEMPTS;

        self::assertSame(0, $w->challenges->expireDue());
        self::assertSame(ChallengeState::Open, $w->challenges->get('c1')->state);
        self::assertSame(1, $w->challenges->expireDue());
    }

    public function testExpiringSkipsWhatTheStoreGivesDueAndNoChallengeHolds(): void
    {
        $store = new BusyStore();
        $w = new World($store);
        $w->challenges->create($w->draft(), World::ana());
        $w->clock->sleep(11 * 86_400);

        $store->extraDue = ['ghost'];

        self::assertSame(1, $w->challenges->expireDue());
        self::assertSame(ChallengeState::Expired, $w->challenges->get('c1')->state);
    }

    public function testAMoveThatChangesNothingWritesNothing(): void
    {
        $store = new BusyStore();
        $w = new World($store);
        $w->challenges->create($w->draft(), World::ana());

        $w->challenges->join('c1', World::bea());

        $updates = $store->updates;
        $w->events->take();

        self::assertTrue($w->challenges->join('c1', World::bea())->isParticipant(World::bea()));
        self::assertSame($updates, $store->updates);
        self::assertSame([], $w->events->take());
    }

    public function testTheChallengesRunWithoutAnyoneListeningToTheEvents(): void
    {
        $w = new World();
        $challenges = new FriendChallenges($w->store, $w->points, $w->friends, $w->moderation, null, new ChallengeRules(), $w->clock);

        $challenges->create($w->draft(), World::ana());
        $challenges->join('c1', World::bea());

        self::assertTrue($challenges->get('c1')->isParticipant(World::bea()));
    }

    public function testAMoveIsTriedAgainWhenAnotherWriterWasFirst(): void
    {
        $store = new BusyStore();
        $w = new World($store);
        $w->challenges->create($w->draft(), World::ana());

        $store->failures = FriendChallenges::ATTEMPTS - 1;

        $w->challenges->join('c1', World::bea());

        self::assertSame(FriendChallenges::ATTEMPTS, $store->updates);
        self::assertTrue($w->challenges->get('c1')->isParticipant(World::bea()));
    }

    public function testAMoveGivesUpAfterThreeAttempts(): void
    {
        $store = new BusyStore();
        $w = new World($store);
        $w->challenges->create($w->draft(), World::ana());

        $store->failures = FriendChallenges::ATTEMPTS;
        $w->events->take();

        try {
            $w->challenges->join('c1', World::bea());
            self::fail('Another writer kept the challenge busy.');
        } catch (ChallengeConflict $challengeConflict) {
            self::assertStringContainsString('3 attempts', $challengeConflict->getMessage());
        }

        self::assertSame(FriendChallenges::ATTEMPTS, $store->updates);
        self::assertSame([], $w->events->take());
    }

    public function testAnUnknownChallengeIsRefused(): void
    {
        $w = new World();

        $this->expectException(ChallengeRefused::class);
        $this->expectExceptionMessageIsOrContains('No challenge "nope" is stored.');

        $w->challenges->join('nope', World::bea());
    }

    public function testVotesAcceptAProofOnceTheVotesNeededApprove(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(validation: Validation::Vote, votes: 2), World::ana());
        $w->challenges->join('c1', World::bea());
        $w->challenges->join('c1', World::cy());
        $w->challenges->submitProof('c1', 'p1', World::bea(), 'upload:44');

        $voted = $w->challenges->approve('c1', 'p1', World::cy());
        self::assertSame(ChallengeState::Open, $voted->state);
        self::assertSame(1000 - 100, $w->points->balance(World::ana()));

        $won = $w->challenges->approve('c1', 'p1', World::ana());
        self::assertSame(ChallengeState::Won, $won->state);
        self::assertSame(100, $w->points->balance(World::bea()));
    }

    public function testAModeratorJudgesWhenTheProofsAreLeftToModerators(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(validation: Validation::Moderator), World::ana());
        $w->challenges->join('c1', World::bea());
        $w->challenges->submitProof('c1', 'p1', World::bea(), 'upload:44');

        try {
            $w->challenges->approve('c1', 'p1', World::ana());
            self::fail('The creator does not judge.');
        } catch (ChallengeRefused) {
            self::assertSame(ChallengeState::Open, $w->challenges->get('c1')->state);
        }

        self::assertSame(ChallengeState::Won, $w->challenges->approve('c1', 'p1', World::mo())->state);
    }

    public function testAModeratorDoesNotJudgeWhatTheCreatorJudges(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());
        $w->challenges->join('c1', World::bea());
        $w->challenges->submitProof('c1', 'p1', World::bea(), 'upload:44');

        $this->expectException(ChallengeRefused::class);

        $w->challenges->reject('c1', 'p1', World::mo());
    }

    public function testAFrozenChallengeKeepsItsPrizeAndStopsEveryMove(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());
        $w->challenges->join('c1', World::bea());
        $w->events->take();

        $frozen = $w->challenges->freeze('c1', World::mo());

        self::assertSame(ChallengeState::Frozen, $frozen->state);
        self::assertSame(100, $w->points->held('friend-challenge:c1'));
        self::assertInstanceOf(ChallengeFrozen::class, $w->events->events[0]);
        self::assertSame('c1', $w->events->events[0]->challenge);
        $w->events->take();

        try {
            $w->challenges->submitProof('c1', 'p1', World::bea(), 'upload:44');
            self::fail('A frozen challenge takes no proof.');
        } catch (ChallengeRefused) {
            $w->clock->sleep(11 * 86_400);
            self::assertSame(0, $w->challenges->expireDue());
        }

        $open = $w->challenges->unfreeze('c1', World::mo());
        self::assertSame(ChallengeState::Open, $open->state);
        self::assertEquals([new ChallengeUnfrozen('c1')], $w->events->events);
        self::assertSame(1, $w->challenges->expireDue());
    }

    public function testARemovedChallengeGivesItsPrizeBack(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());
        $w->challenges->freeze('c1', World::mo());

        self::assertSame(ChallengeState::Removed, $w->challenges->remove('c1', World::mo())->state);
        self::assertSame(1000, $w->points->balance(World::ana()));
    }

    public function testAModeratorRemovesAProof(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());
        $w->challenges->join('c1', World::bea());
        $w->challenges->submitProof('c1', 'p1', World::bea(), 'upload:44');
        $w->events->take();

        $challenge = $w->challenges->removeProof('c1', 'p1', World::mo());

        self::assertSame(ProofState::Removed, $challenge->proof('p1')?->state);
        self::assertSame([ProofDecided::class], $w->events->take());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function moderatorMoves(): iterable
    {
        yield 'freeze' => ['freeze'];
        yield 'unfreeze' => ['unfreeze'];
        yield 'remove' => ['remove'];
        yield 'remove a proof' => ['removeProof'];
    }

    #[DataProvider('moderatorMoves')]
    public function testOnlyAModeratorModerates(string $move): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());
        $w->challenges->join('c1', World::bea());
        $w->challenges->submitProof('c1', 'p1', World::bea(), 'upload:44');

        try {
            match ($move) {
                'freeze' => $w->challenges->freeze('c1', World::ana()),
                'unfreeze' => $w->challenges->unfreeze('c1', World::ana()),
                'remove' => $w->challenges->remove('c1', World::ana()),
                default => $w->challenges->removeProof('c1', 'p1', World::ana()),
            };
            self::fail('Ana does not moderate.');
        } catch (ChallengeRefused $challengeRefused) {
            self::assertStringContainsString('member:ana is not one', $challengeRefused->getMessage());
        }

        self::assertSame(3, $w->challenges->get('c1')->version);
    }

    public function testAReportGoesToTheModerators(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());
        $w->challenges->join('c1', World::bea());
        $w->challenges->submitProof('c1', 'p1', World::bea(), 'upload:44');

        $w->challenges->report('c1', World::cy(), ReportReason::Cheating, 'p1', 'Not her minis.');
        $w->challenges->report('c1', World::eve(), ReportReason::Spam);

        self::assertCount(2, $w->moderation->reports);
        $report = $w->moderation->reports[0];
        self::assertInstanceOf(Report::class, $report);
        self::assertSame(['c1', 'member:cy', ReportReason::Cheating, 'p1', 'Not her minis.', '2026-11-14T01:00:00.000000Z'], [$report->challenge, $report->reporter->toString(), $report->reason, $report->proof, $report->note, $report->at->format('Y-m-d\TH:i:s.u\Z')]);
        self::assertNull($w->moderation->reports[1]->proof);
    }

    public function testAReportOfAnUnknownProofIsRefused(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());

        try {
            $w->challenges->report('c1', World::cy(), ReportReason::Cheating, 'p9');
            self::fail('The proof does not exist.');
        } catch (ChallengeRefused) {
            self::assertSame([], $w->moderation->reports);
        }
    }

    public function testSettlingAgainPaysAPrizeTheEscrowFailedToPay(): void
    {
        $w0 = new World();
        $escrow = new FailingEscrow($w0->points);
        $w = new World(escrow: $escrow);
        $w->challenges->create($w->draft(), World::ana());
        $w->challenges->join('c1', World::bea());
        $w->challenges->submitProof('c1', 'p1', World::bea(), 'upload:44');

        try {
            $w->challenges->approve('c1', 'p1', World::ana());
            self::fail('The escrow fails once.');
        } catch (RuntimeException) {
            self::assertSame(ChallengeState::Won, $w->challenges->get('c1')->state);
            self::assertSame(0, $w0->points->balance(World::bea()));
        }

        $w->challenges->settle('c1');
        $w->challenges->settle('c1');

        self::assertSame(100, $w0->points->balance(World::bea()));
        self::assertSame(900, $w0->points->balance(World::ana()));
    }

    public function testSettlingAgainGivesBackAPrizeTheEscrowFailedToGiveBack(): void
    {
        $w0 = new World();
        $escrow = new FailingEscrow($w0->points);
        $w = new World(escrow: $escrow);
        $w->challenges->create($w->draft(), World::ana());
        $w->challenges->join('c1', World::bea());

        try {
            $w->challenges->cancel('c1', World::ana());
            self::fail('The escrow fails once.');
        } catch (RuntimeException) {
            self::assertSame(900, $w0->points->balance(World::ana()));
        }

        $w->challenges->settle('c1');

        self::assertSame(1000, $w0->points->balance(World::ana()));
    }

    public function testSettlingAnOpenChallengeMovesNoPoint(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());

        $w->challenges->settle('c1');

        self::assertSame(100, $w->points->held('friend-challenge:c1'));
    }

    public function testAnOpenChallengeTakesAnyMemberButItsCreator(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(visibility: Visibility::Open), World::ana());

        self::assertTrue($w->challenges->join('c1', World::eve())->isParticipant(World::eve()));

        $this->expectException(ChallengeRefused::class);

        $w->challenges->join('c1', World::ana());
    }
}
