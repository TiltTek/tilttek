<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tilttek\Bounties\Bounties;
use Tilttek\Bounties\Bounty;
use Tilttek\Bounties\BountyHold;
use Tilttek\Bounties\BountyRules;
use Tilttek\Bounties\BountyState;
use Tilttek\Bounties\Event\AwardGiven;
use Tilttek\Bounties\Event\BountyAwarded;
use Tilttek\Bounties\Event\BountyClaimed;
use Tilttek\Bounties\Event\BountyClosed;
use Tilttek\Bounties\Event\BountyOffered;
use Tilttek\Bounties\Exception\BountyConflict;
use Tilttek\Bounties\Exception\BountyRefused;
use Tilttek\Bounties\Exception\InvalidBounty;
use Tilttek\Bounties\Tests\Unit\Fixture\BusyStore;
use Tilttek\Bounties\Tests\Unit\Fixture\FailingEscrow;
use Tilttek\Bounties\Tests\Unit\Fixture\World;
use Tilttek\Points\Exception\InsufficientPoints;

#[CoversClass(Bounties::class)]
#[CoversClass(BountyOffered::class)]
#[CoversClass(BountyClaimed::class)]
#[CoversClass(AwardGiven::class)]
#[CoversClass(BountyAwarded::class)]
#[CoversClass(BountyClosed::class)]
final class BountiesTest extends TestCase
{
    public function testAnOfferedBountyHoldsItsPoints(): void
    {
        $w = new World();

        $bounty = $w->bounties->offer($w->draft(), World::ana());

        self::assertSame(BountyState::Open, $bounty->state);
        self::assertSame(900, $w->points->balance(World::ana()));
        self::assertSame(100, $w->points->held('bounty:b1'));
        self::assertEquals([new BountyOffered('b1', World::ana(), World::question())], $w->events->events);
        self::assertSame($bounty->toArray(), $w->bounties->get('b1')->toArray());
    }

    public function testAnOfferSentAgainGivesBackTheBountyAndHoldsNothingMore(): void
    {
        $w = new World();
        $first = $w->bounties->offer($w->draft(), World::ana());
        $w->events->take();

        self::assertSame($first->toArray(), $w->bounties->offer($w->draft(), World::ana())->toArray());
        self::assertSame(900, $w->points->balance(World::ana()));
        self::assertSame([], $w->events->take());
    }

    public function testAnIdentifierNamesOneBounty(): void
    {
        $w = new World();
        $w->bounties->offer($w->draft(), World::ana());

        $this->expectException(BountyRefused::class);
        $this->expectExceptionMessageIsOrContains('exists already, with other values');

        $w->bounties->offer($w->draft(amount: 50), World::ana());
    }

    public function testASubjectHasOneOpenBountyAtATime(): void
    {
        $w = new World();
        $w->bounties->offer($w->draft(), World::ana());

        try {
            $w->bounties->offer($w->draft('b2'), World::ana());
            self::fail('The question has an open bounty.');
        } catch (BountyRefused $bountyRefused) {
            self::assertStringContainsString('question:42 has an open bounty already, "b1"', $bountyRefused->getMessage());
            self::assertSame(900, $w->points->balance(World::ana()));
        }

        $w->bounties->cancel('b1', World::ana());

        self::assertSame(BountyState::Open, $w->bounties->offer($w->draft('b2'), World::ana())->state);
    }

    public function testABountyThePointsDoNotCoverIsNotOffered(): void
    {
        $w = new World();

        try {
            $w->bounties->offer($w->draft(amount: 1001), World::ana());
            self::fail('Ana holds 1 000 points.');
        } catch (InsufficientPoints) {
            self::assertNull($w->store->get('b1'));
            self::assertSame([], $w->events->take());
        }
    }

    public function testADraftOutsideTheRulesIsRefusedBeforeAnyPointMoves(): void
    {
        $w = new World(rules: new BountyRules(largestPrize: 50));

        try {
            $w->bounties->offer($w->draft(), World::ana());
            self::fail('The bounty is over the largest.');
        } catch (InvalidBounty) {
            self::assertSame(1000, $w->points->balance(World::ana()));
        }
    }

    public function testAnotherWriterOfferingTheSameBountyFirstGivesItBack(): void
    {
        $store = new BusyStore();
        $w = new World($store);
        $store->addedFirst = Bounty::offer($w->draft(), World::ana(), $w->clock->now(), new BountyRules());

        $bounty = $w->bounties->offer($w->draft(), World::ana());

        self::assertSame($store->get('b1')?->toArray(), $bounty->toArray());
        self::assertSame(900, $w->points->balance(World::ana()));
        self::assertSame([], $w->events->take());
    }

    public function testAnotherWriterTakingTheSubjectFirstGivesThePointsBack(): void
    {
        $store = new BusyStore();
        $w = new World($store);
        $store->addedFirst = Bounty::offer($w->draft('other'), World::bea(), $w->clock->now(), new BountyRules());

        try {
            $w->bounties->offer($w->draft(), World::ana());
            self::fail('Another bounty took the question.');
        } catch (BountyConflict) {
            self::assertSame(1000, $w->points->balance(World::ana()));
            self::assertSame(0, $w->points->held('bounty:b1'));
        }
    }

    public function testAnotherWriterOfferingAnotherBountyOfTheSameIdentifierKeepsItsPoints(): void
    {
        $store = new BusyStore();
        $w = new World($store);
        $other = Bounty::offer($w->draft(subject: '7'), World::ana(), $w->clock->now(), new BountyRules());
        $w->points->reserve(BountyHold::of($other));
        $store->addedFirst = $other;

        try {
            $w->bounties->offer($w->draft(), World::ana());
            self::fail('Another bounty took the identifier.');
        } catch (BountyConflict) {
            self::assertSame(100, $w->points->held('bounty:b1'));
            self::assertSame(900, $w->points->balance(World::ana()));
        }
    }

    public function testTheWholeAmountAwardedToOneClaimPaysItAtOnce(): void
    {
        $w = new World();
        $w->bounties->offer($w->draft(), World::ana());
        $w->bounties->claim('b1', 'answer-1', World::bea(), 'answer:901');
        $w->events->take();

        $bounty = $w->bounties->award('b1', 'award-1', 'answer-1', 100, World::ana());

        self::assertSame(BountyState::Awarded, $bounty->state);
        self::assertSame(100, $w->points->balance(World::bea()));
        self::assertSame(900, $w->points->balance(World::ana()));
        self::assertEquals([
            new AwardGiven('b1', 'award-1', 'answer-1', 100),
            new BountyAwarded('b1', World::bea(), 100, 'points'),
            new BountyClosed('b1', BountyState::Awarded, 0),
        ], $w->events->events);
    }

    public function testABountySharedBetweenWinnersGivesTheRestBackWhenClosed(): void
    {
        $w = new World();
        $w->bounties->offer($w->draft(), World::ana());
        $w->bounties->claim('b1', 'answer-1', World::bea(), 'answer:901');
        $w->bounties->claim('b1', 'answer-2', World::cy(), 'answer:902');
        $w->bounties->claim('b1', 'answer-3', World::bea(), 'answer:903');
        $w->bounties->award('b1', 'award-1', 'answer-1', 30, World::ana());
        $w->bounties->award('b1', 'award-2', 'answer-2', 40, World::ana());
        $w->bounties->award('b1', 'award-3', 'answer-3', 10, World::ana());
        $w->events->take();

        $bounty = $w->bounties->close('b1', World::ana());

        self::assertSame(BountyState::Awarded, $bounty->state);
        self::assertSame(40, $w->points->balance(World::bea()));
        self::assertSame(40, $w->points->balance(World::cy()));
        self::assertSame(920, $w->points->balance(World::ana()));
        self::assertEquals([
            new BountyAwarded('b1', World::bea(), 40, 'points'),
            new BountyAwarded('b1', World::cy(), 40, 'points'),
            new BountyClosed('b1', BountyState::Awarded, 20),
        ], $w->events->events);
    }

    public function testEachMoveIsAnnouncedOnceAndAMoveMadeAgainChangesNothing(): void
    {
        $w = new World();
        $w->bounties->offer($w->draft(), World::ana());
        $w->events->take();

        $claimed = $w->bounties->claim('b1', 'answer-1', World::bea(), 'answer:901');
        self::assertEquals([new BountyClaimed('b1', 'answer-1', World::bea())], $w->events->events);
        $w->events->take();
        self::assertSame($claimed->version, $w->bounties->claim('b1', 'answer-1', World::bea(), 'answer:901')->version);

        $w->bounties->award('b1', 'award-1', 'answer-1', 30, World::ana());
        self::assertSame([AwardGiven::class], $w->events->take());
        $w->bounties->award('b1', 'award-1', 'answer-1', 30, World::ana());
        self::assertSame([], $w->events->take());

        $w->bounties->close('b1', World::ana());
        $w->events->take();
        $w->bounties->close('b1', World::ana());
        self::assertSame([], $w->events->take());
    }

    public function testABountyExpiresOnceItsGraceIsOver(): void
    {
        $w = new World();
        $w->bounties->offer($w->draft('b1'), World::ana());
        $w->clock->sleep(86_400);
        $w->bounties->offer($w->draft('b2', subject: '43'), World::ana());
        $w->bounties->claim('b2', 'answer-1', World::bea(), 'answer:901');
        $w->bounties->award('b2', 'award-1', 'answer-1', 25, World::ana());

        try {
            $w->bounties->expire('b1');
            self::fail('The bounty is still open.');
        } catch (BountyRefused) {
            self::assertSame(0, $w->bounties->expireDue());
        }

        $w->clock->sleep(7 * 86_400);
        $w->events->take();

        self::assertSame(1, $w->bounties->expireDue(1));
        self::assertSame(BountyState::Expired, $w->bounties->get('b1')->state);
        self::assertEquals([new BountyClosed('b1', BountyState::Expired, 100)], $w->events->events);
        self::assertSame(0, $w->bounties->expireDue());

        $w->clock->sleep(86_400);
        $w->events->take();

        self::assertSame(1, $w->bounties->expireDue());
        self::assertSame(BountyState::Awarded, $w->bounties->get('b2')->state);
        self::assertSame(25, $w->points->balance(World::bea()));
        self::assertSame(975, $w->points->balance(World::ana()));
        self::assertEquals([new BountyAwarded('b2', World::bea(), 25, 'points'), new BountyClosed('b2', BountyState::Awarded, 75)], $w->events->events);
    }

    public function testExpiringRunsAtLeastOneAndAtMostFiveHundred(): void
    {
        $w = new World();

        foreach (range(1, 3) as $n) {
            $w->bounties->offer($w->draft('b'.$n, 10, (string) $n), World::ana());
        }

        $w->clock->sleep(9 * 86_400);

        self::assertSame(1, $w->bounties->expireDue(0));
        self::assertSame(2, $w->bounties->expireDue(Bounties::MAX_BATCH + 10));
    }

    public function testExpiringAsksTheStoreForOneToFiveHundred(): void
    {
        $store = new BusyStore();
        $w = new World($store);

        foreach ([[0, 1], [7, 7], [Bounties::MAX_BATCH, 500], [10_000, 500]] as [$asked, $sent]) {
            $w->bounties->expireDue($asked);
            self::assertSame($sent, $store->limit);
        }

        $w->bounties->expireDue();
        self::assertSame(100, $store->limit);
    }

    public function testAHoldLeftByAnEarlierAttemptGoesBackWhenAnotherBountyTakesTheSubject(): void
    {
        $store = new BusyStore();
        $w = new World($store);
        $w->points->reserve(BountyHold::of(Bounty::offer($w->draft(), World::ana(), $w->clock->now(), new BountyRules())));

        $store->addedFirst = Bounty::offer($w->draft('other'), World::bea(), $w->clock->now(), new BountyRules());

        try {
            $w->bounties->offer($w->draft(), World::ana());
            self::fail('Another bounty took the question.');
        } catch (BountyConflict) {
            self::assertSame(1000, $w->points->balance(World::ana()));
        }
    }

    public function testAnotherBountyOfTheSameIdentifierKeepsTheHoldOfItsKey(): void
    {
        $store = new BusyStore();
        $w = new World($store);
        $store->addedFirst = Bounty::offer($w->draft(subject: '9'), World::bea(), $w->clock->now(), new BountyRules());

        try {
            $w->bounties->offer($w->draft(), World::ana());
            self::fail('Another bounty took the identifier.');
        } catch (BountyConflict) {
            self::assertSame(100, $w->points->held('bounty:b1'));
        }
    }

    public function testExpiringLeavesABountyAnotherWriterKeepsBusy(): void
    {
        $store = new BusyStore();
        $w = new World($store);
        $w->bounties->offer($w->draft(), World::ana());
        $w->clock->sleep(9 * 86_400);

        $store->failures = Bounties::ATTEMPTS;

        self::assertSame(0, $w->bounties->expireDue());
        self::assertSame(1, $w->bounties->expireDue());
    }

    public function testExpiringSkipsWhatTheStoreGivesDueAndNoBountyHolds(): void
    {
        $store = new BusyStore();
        $w = new World($store);
        $w->bounties->offer($w->draft(), World::ana());
        $w->clock->sleep(9 * 86_400);

        $store->extraDue = ['ghost'];

        self::assertSame(1, $w->bounties->expireDue());
        self::assertSame(BountyState::Expired, $w->bounties->get('b1')->state);
    }

    public function testAMoveThatChangesNothingWritesNothing(): void
    {
        $store = new BusyStore();
        $w = new World($store);
        $w->bounties->offer($w->draft(), World::ana());

        $w->bounties->claim('b1', 'answer-1', World::bea(), 'answer:90');

        $updates = $store->updates;
        $w->events->take();

        $w->bounties->claim('b1', 'answer-1', World::bea(), 'answer:90');

        self::assertSame($updates, $store->updates);
        self::assertSame([], $w->events->take());
    }

    public function testTheBountiesRunWithoutAnyoneListeningToTheEvents(): void
    {
        $w = new World();
        $bounties = new Bounties($w->store, $w->points, null, new BountyRules(), $w->clock);

        $bounties->offer($w->draft(), World::ana());
        $bounties->claim('b1', 'answer-1', World::bea(), 'answer:90');

        self::assertCount(1, $bounties->get('b1')->claims);
    }

    public function testAMoveIsTriedAgainThenGivenUp(): void
    {
        $store = new BusyStore();
        $w = new World($store);
        $w->bounties->offer($w->draft(), World::ana());

        $store->failures = Bounties::ATTEMPTS - 1;

        $w->bounties->claim('b1', 'answer-1', World::bea(), 'answer:901');
        self::assertSame(Bounties::ATTEMPTS, $store->updates);

        $store->failures = Bounties::ATTEMPTS;
        $w->events->take();

        try {
            $w->bounties->claim('b1', 'answer-2', World::cy(), 'answer:902');
            self::fail('Another writer kept the bounty busy.');
        } catch (BountyConflict $bountyConflict) {
            self::assertStringContainsString('3 attempts', $bountyConflict->getMessage());
            self::assertSame(2 * Bounties::ATTEMPTS, $store->updates);
            self::assertSame([], $w->events->take());
        }
    }

    public function testCancellingGivesThePointsBack(): void
    {
        $w = new World();
        $w->bounties->offer($w->draft(), World::ana());
        $w->events->take();

        self::assertSame(BountyState::Cancelled, $w->bounties->cancel('b1', World::ana())->state);
        self::assertSame(1000, $w->points->balance(World::ana()));
        self::assertEquals([new BountyClosed('b1', BountyState::Cancelled, 100)], $w->events->events);
    }

    public function testAnUnknownBountyIsRefused(): void
    {
        $w = new World();

        $this->expectException(BountyRefused::class);
        $this->expectExceptionMessageIsOrContains('No bounty "nope" is stored.');

        $w->bounties->claim('nope', 'answer-1', World::bea(), 'answer:901');
    }

    public function testSettlingAgainPaysWhatTheEscrowFailedToPay(): void
    {
        $w0 = new World();
        $w = new World(escrow: new FailingEscrow($w0->points));
        $w->bounties->offer($w->draft(), World::ana());
        $w->bounties->claim('b1', 'answer-1', World::bea(), 'answer:901');

        try {
            $w->bounties->award('b1', 'award-1', 'answer-1', 60, World::ana());
            $w->bounties->close('b1', World::ana());
            self::fail('The escrow fails once.');
        } catch (RuntimeException) {
            self::assertSame(BountyState::Awarded, $w->bounties->get('b1')->state);
            self::assertSame(0, $w0->points->balance(World::bea()));
        }

        $w->bounties->settle('b1');
        $w->bounties->settle('b1');

        self::assertSame(60, $w0->points->balance(World::bea()));
        self::assertSame(940, $w0->points->balance(World::ana()));
    }

    public function testSettlingAgainGivesBackWhatTheEscrowFailedToGiveBack(): void
    {
        $w0 = new World();
        $w = new World(escrow: new FailingEscrow($w0->points));
        $w->bounties->offer($w->draft(), World::ana());

        try {
            $w->bounties->cancel('b1', World::ana());
            self::fail('The escrow fails once.');
        } catch (RuntimeException) {
            self::assertSame(900, $w0->points->balance(World::ana()));
        }

        $w->bounties->settle('b1');

        self::assertSame(1000, $w0->points->balance(World::ana()));
    }

    public function testSettlingAnOpenBountyMovesNoPoint(): void
    {
        $w = new World();
        $w->bounties->offer($w->draft(), World::ana());

        $w->bounties->settle('b1');

        self::assertSame(100, $w->points->held('bounty:b1'));
    }
}
