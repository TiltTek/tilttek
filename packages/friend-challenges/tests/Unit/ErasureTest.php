<?php

declare(strict_types=1);

namespace Tilttek\FriendChallenges\Tests\Unit;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tilttek\FriendChallenges\Challenge;
use Tilttek\FriendChallenges\ChallengeState;
use Tilttek\FriendChallenges\Event\ChallengeEnded;
use Tilttek\FriendChallenges\Event\ProofDecided;
use Tilttek\FriendChallenges\FriendChallenges;
use Tilttek\FriendChallenges\ProofState;
use Tilttek\FriendChallenges\Pseudonym;
use Tilttek\FriendChallenges\Store\InMemoryChallengeStore;
use Tilttek\FriendChallenges\Tests\Unit\Fixture\BusyStore;
use Tilttek\FriendChallenges\Tests\Unit\Fixture\FailingEscrow;
use Tilttek\FriendChallenges\Tests\Unit\Fixture\World;
use Tilttek\FriendChallenges\Validation;

#[CoversClass(FriendChallenges::class)]
#[CoversClass(Challenge::class)]
#[CoversClass(Pseudonym::class)]
#[CoversClass(InMemoryChallengeStore::class)]
final class ErasureTest extends TestCase
{
    public function testAPseudonymIsOfTheErasedKindAndDrawnAnewEachTime(): void
    {
        $first = Pseudonym::draw();
        $second = Pseudonym::draw();

        self::assertSame('erased', $first->key);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/D', $first->id);
        self::assertFalse($first->equals($second));
        self::assertTrue(Pseudonym::isErased($first));
        self::assertFalse(Pseudonym::isErased(World::ana()));
    }

    public function testAnErasedCreatorsOpenChallengeIsRemovedAndItsPrizeGivenBackFirst(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());
        $w->challenges->join('c1', World::bea());
        $w->events->take();

        self::assertSame(1, $w->challenges->erase(World::ana()));

        $challenge = $w->challenges->get('c1');
        self::assertSame(ChallengeState::Removed, $challenge->state);
        self::assertSame(1000, $w->points->balance(World::ana()));
        self::assertSame(0, $w->points->held('friend-challenge:c1'));
        self::assertTrue(Pseudonym::isErased($challenge->creator));
        self::assertFalse($challenge->involves(World::ana()));
        self::assertTrue($challenge->isParticipant(World::bea()));
        self::assertSame(4, $challenge->version);
        self::assertSame([ChallengeEnded::class], $w->events->take());
        self::assertSame($challenge->toArray(), Challenge::fromArray($challenge->toArray())->toArray());

        self::assertSame(0, $w->challenges->erase(World::ana()));
        $w->challenges->settle('c1');
        self::assertSame(1000, $w->points->balance(World::ana()));
    }

    public function testAnErasedParticipantsWaitingProofsAreRemovedAndTheirVotesStayCounted(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(validation: Validation::Vote, votes: 2), World::ana());
        $w->challenges->join('c1', World::bea());
        $w->challenges->join('c1', World::cy());
        $w->challenges->submitProof('c1', 'p1', World::bea(), 'upload:41');
        $w->challenges->submitProof('c1', 'p2', World::cy(), 'upload:42');
        $w->challenges->approve('c1', 'p2', World::bea());
        $w->events->take();

        self::assertSame(1, $w->challenges->erase(World::bea()));

        $challenge = $w->challenges->get('c1');
        $bea = $challenge->participants[0];
        self::assertSame(ChallengeState::Open, $challenge->state);
        self::assertTrue(Pseudonym::isErased($bea));
        self::assertFalse($challenge->involves(World::bea()));
        self::assertTrue($challenge->isInvited($bea));
        self::assertSame(ProofState::Removed, $challenge->proof('p1')?->state);
        self::assertTrue($challenge->proof('p1')->participant->equals($bea));
        self::assertSame($bea->toString(), $challenge->proof('p1')->evidence);
        self::assertSame([$bea->toString()], $challenge->proof('p2')?->approvals);
        self::assertSame('upload:42', $challenge->proof('p2')->evidence);
        self::assertSame([ProofDecided::class], $w->events->take());

        $won = $w->challenges->approve('c1', 'p2', World::ana());

        self::assertSame(ChallengeState::Won, $won->state);
        self::assertSame(100, $w->points->balance(World::cy()));
    }

    public function testAnErasedWinnerKeepsWhatWasPaidAndIsNeverPaidAgain(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());
        $w->challenges->join('c1', World::bea());
        $w->challenges->submitProof('c1', 'p1', World::bea(), 'upload:41');
        $w->challenges->approve('c1', 'p1', World::ana());
        $w->events->take();

        self::assertSame(1, $w->challenges->erase(World::bea()));

        $challenge = $w->challenges->get('c1');
        self::assertInstanceOf(ResourceReference::class, $challenge->winner);
        self::assertTrue(Pseudonym::isErased($challenge->winner));
        self::assertTrue($challenge->winner->equals($challenge->participants[0]));
        self::assertSame(ProofState::Accepted, $challenge->proof('p1')?->state);
        self::assertSame([], $w->events->take());

        $w->challenges->settle('c1');

        self::assertSame(100, $w->points->balance(World::bea()));
        self::assertSame(0, $w->points->balance($challenge->winner));
    }

    public function testAChallengeWhoseEscrowFailedIsSettledBeforeItsWinnerIsErased(): void
    {
        $w0 = new World();
        $escrow = new FailingEscrow($w0->points);
        $w = new World(escrow: $escrow);
        $w->challenges->create($w->draft(), World::ana());
        $w->challenges->join('c1', World::bea());
        $w->challenges->submitProof('c1', 'p1', World::bea(), 'upload:41');

        try {
            $w->challenges->approve('c1', 'p1', World::ana());
            self::fail('The escrow fails once.');
        } catch (RuntimeException) {
            self::assertSame(0, $w0->points->balance(World::bea()));
        }

        $w->challenges->erase(World::bea());

        self::assertSame(100, $w0->points->balance(World::bea()));
        self::assertTrue(Pseudonym::isErased($w->challenges->get('c1')->winner ?? World::bea()));
    }

    public function testAnInviteeWhoDeclinedIsReplacedEverywhereAndEachChallengeDrawsItsOwnPseudonym(): void
    {
        $w = new World();
        $w->challenges->create($w->draft('c1'), World::ana());
        $w->challenges->create($w->draft('c2'), World::ana());
        $w->challenges->decline('c1', World::dee());

        self::assertSame(2, $w->challenges->erase(World::dee()));

        $first = $w->challenges->get('c1');
        $second = $w->challenges->get('c2');
        self::assertTrue($first->invitees[2]->equals($first->declined[0]));
        self::assertTrue(Pseudonym::isErased($first->invitees[2]));
        self::assertTrue(Pseudonym::isErased($second->invitees[2]));
        self::assertFalse($first->invitees[2]->equals($second->invitees[2]));
        self::assertTrue($first->invitees[0]->equals(World::bea()));
        self::assertTrue($first->creator->equals(World::ana()));
        self::assertSame(ChallengeState::Open, $second->state);
        self::assertSame(2, $second->version);
        self::assertSame(800, $w->points->balance(World::ana()));
    }

    public function testAMemberNamedByNoChallengeErasesNothing(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());

        self::assertSame(0, $w->challenges->erase(World::eve()));
        self::assertSame(1, $w->challenges->get('c1')->version);
    }

    public function testAnErasureGoesThroughBoundedBatches(): void
    {
        $store = new BusyStore();
        $w = new World($store);

        foreach (['c1', 'c2', 'c3'] as $id) {
            $w->challenges->create($w->draft($id, prize: 10), World::ana());
        }

        self::assertSame(2, $w->challenges->erase(World::bea(), 2));
        self::assertSame(2, $store->limit);
        self::assertSame(1, $w->challenges->erase(World::bea(), 0));
        self::assertSame(1, $store->limit);
        self::assertSame(0, $w->challenges->erase(World::bea(), 10_000));
        self::assertSame(FriendChallenges::MAX_BATCH, $store->limit);
        self::assertSame(970, $w->points->balance(World::ana()));
    }

    public function testAnErasureWithoutALimitTakesAHundredChallenges(): void
    {
        $store = new BusyStore();
        $w = new World($store);

        $w->challenges->erase(World::bea());

        self::assertSame(100, $store->limit);
    }

    public function testEveryProofOfTheMemberWaitingForReviewIsRemovedAndAnnounced(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());
        $w->challenges->join('c1', World::bea());
        $w->challenges->submitProof('c1', 'p1', World::bea(), 'upload:41');
        $w->challenges->submitProof('c1', 'p2', World::bea(), 'upload:42');
        $w->events->take();

        self::assertSame(1, $w->challenges->erase(World::bea()));

        self::assertSame([ProofDecided::class, ProofDecided::class], $w->events->take());
    }

    public function testLeavingChangesNothingWhenNothingOfTheMemberWaits(): void
    {
        $w = new World();
        $challenge = $w->challenges->create($w->draft(), World::ana());
        $joined = $w->challenges->join('c1', World::bea());
        $at = $w->in('+1 hour');

        self::assertSame($challenge, $challenge->leave(World::bea(), $at));
        self::assertSame($joined, $joined->leave(World::bea(), $at));
        self::assertSame($joined, $joined->pseudonymized(World::eve(), Pseudonym::draw()));

        $cancelled = $challenge->cancel(World::ana(), $at);

        self::assertSame($cancelled, $cancelled->leave(World::ana(), $at));
    }

    public function testAFrozenChallengeOfAnErasedCreatorIsRemoved(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());
        $w->challenges->freeze('c1', World::mo());

        $w->challenges->erase(World::ana());

        self::assertSame(ChallengeState::Removed, $w->challenges->get('c1')->state);
        self::assertSame(1000, $w->points->balance(World::ana()));
    }

    public function testOnlyTheWaitingProofsOfTheMemberAreRemoved(): void
    {
        $w = new World();
        $w->challenges->create($w->draft(), World::ana());
        $w->challenges->join('c1', World::bea());
        $w->challenges->join('c1', World::cy());
        $w->challenges->submitProof('c1', 'p1', World::bea(), 'upload:41');
        $w->challenges->reject('c1', 'p1', World::ana());
        $w->challenges->submitProof('c1', 'p2', World::bea(), 'upload:42');
        $w->challenges->submitProof('c1', 'p3', World::cy(), 'upload:43');

        $before = $w->challenges->get('c1');

        $left = $before->leave(World::bea(), $w->in('+1 hour'));

        self::assertSame(ProofState::Rejected, $left->proof('p1')?->state);
        self::assertSame(ProofState::Removed, $left->proof('p2')?->state);
        self::assertSame(ProofState::Pending, $left->proof('p3')?->state);
        self::assertSame($before->version + 1, $left->version);
    }

    public function testTheMemoryStoreListsTheChallengesNamingTheMemberUpToTheLimit(): void
    {
        $store = new InMemoryChallengeStore();
        $w = new World($store);

        foreach (['1', 'c2', 'c3'] as $id) {
            $w->challenges->create($w->draft($id, prize: 10), World::ana());
        }

        self::assertSame(['1', 'c2'], $store->involving(World::cy(), 2));
        self::assertSame(['1', 'c2', 'c3'], $store->involving(World::ana(), 5));
        self::assertSame([], $store->involving(World::eve(), 5));
    }
}
