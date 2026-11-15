<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tilttek\Bounties\Award;
use Tilttek\Bounties\Bounties;
use Tilttek\Bounties\Bounty;
use Tilttek\Bounties\BountyState;
use Tilttek\Bounties\Claim;
use Tilttek\Bounties\Event\BountyAwarded;
use Tilttek\Bounties\Event\BountyClosed;
use Tilttek\Bounties\Pseudonym;
use Tilttek\Bounties\Store\InMemoryBountyStore;
use Tilttek\Bounties\Tests\Unit\Fixture\BusyStore;
use Tilttek\Bounties\Tests\Unit\Fixture\FailingEscrow;
use Tilttek\Bounties\Tests\Unit\Fixture\World;

#[CoversClass(Bounties::class)]
#[CoversClass(Bounty::class)]
#[CoversClass(Pseudonym::class)]
#[CoversClass(InMemoryBountyStore::class)]
final class ErasureTest extends TestCase
{
    public function testAPseudonymIsOfTheErasedKindAndDrawnAnewEachTime(): void
    {
        $first = Pseudonym::draw();

        self::assertSame('erased', $first->key);
        self::assertMatchesRegularExpression('/^[0-9a-f]{16}$/D', $first->id);
        self::assertFalse($first->equals(Pseudonym::draw()));
        self::assertTrue(Pseudonym::isErased($first));
        self::assertFalse(Pseudonym::isErased(World::ana()));
    }

    public function testAnErasureWithoutALimitTakesAHundredBounties(): void
    {
        $store = new BusyStore();
        $w = new World($store);

        $w->bounties->erase(World::bea());

        self::assertSame(100, $store->limit);
    }

    public function testAnErasedSponsorsBountyWithoutClaimsIsCancelledAndGivenBack(): void
    {
        $w = new World();
        $w->bounties->offer($w->draft(), World::ana());
        $w->events->take();

        self::assertSame(1, $w->bounties->erase(World::ana()));

        $bounty = $w->bounties->get('b1');
        self::assertSame(BountyState::Cancelled, $bounty->state);
        self::assertSame(1000, $w->points->balance(World::ana()));
        self::assertTrue(Pseudonym::isErased($bounty->sponsor));
        self::assertFalse($bounty->involves(World::ana()));
        self::assertSame(3, $bounty->version);
        self::assertEquals([new BountyClosed('b1', BountyState::Cancelled, 100)], $w->events->events);
        self::assertSame($bounty->toArray(), Bounty::fromArray($bounty->toArray())->toArray());
        self::assertSame(0, $w->bounties->erase(World::ana()));
    }

    public function testAnErasedSponsorsBountyWithClaimsAndNoAwardExpires(): void
    {
        $w = new World();
        $w->bounties->offer($w->draft(), World::ana());
        $w->bounties->claim('b1', 'answer-1', World::bea(), 'answer:901');

        $w->bounties->erase(World::ana());

        self::assertSame(BountyState::Expired, $w->bounties->get('b1')->state);
        self::assertSame(1000, $w->points->balance(World::ana()));
        self::assertSame(0, $w->points->balance(World::bea()));
    }

    public function testAnErasedSponsorsAwardsArePaidAndTheRestGivenBack(): void
    {
        $w = new World();
        $w->bounties->offer($w->draft(), World::ana());
        $w->bounties->claim('b1', 'answer-1', World::bea(), 'answer:901');
        $w->bounties->award('b1', 'award-1', 'answer-1', 60, World::ana());
        $w->events->take();

        $w->bounties->erase(World::ana());

        $bounty = $w->bounties->get('b1');
        self::assertSame(BountyState::Awarded, $bounty->state);
        self::assertSame(60, $w->points->balance(World::bea()));
        self::assertSame(940, $w->points->balance(World::ana()));
        self::assertEquals([new BountyAwarded('b1', World::bea(), 60, 'points'), new BountyClosed('b1', BountyState::Awarded, 40)], $w->events->events);

        $w->bounties->settle('b1');

        self::assertSame(60, $w->points->balance(World::bea()));
        self::assertSame(0, $w->points->balance($bounty->sponsor));
    }

    public function testAnErasedClaimantsClaimsAndTheirAwardsAreWithdrawnFromAnOpenBounty(): void
    {
        $w = new World();
        $w->bounties->offer($w->draft(), World::ana());
        $w->bounties->claim('b1', 'answer-1', World::bea(), 'answer:901');
        $w->bounties->claim('b1', 'answer-2', World::cy(), 'answer:902');
        $w->bounties->claim('b1', 'answer-3', World::bea(), 'answer:903');
        $w->bounties->award('b1', 'award-1', 'answer-1', 30, World::ana());
        $w->bounties->award('b1', 'award-2', 'answer-2', 20, World::ana());

        $before = $w->bounties->get('b1');
        $w->events->take();

        self::assertSame(1, $w->bounties->erase(World::bea()));

        $bounty = $w->bounties->get('b1');
        self::assertSame(BountyState::Open, $bounty->state);
        self::assertSame(['answer-2'], array_map(static fn (Claim $claim): string => $claim->id, $bounty->claims));
        self::assertSame(['award-2'], array_map(static fn (Award $award): string => $award->id, $bounty->awards));
        self::assertSame(20, $bounty->awarded());
        self::assertSame($before->version + 1, $bounty->version);
        self::assertFalse($bounty->involves(World::bea()));
        self::assertSame([], $w->events->take());
        self::assertSame(0, $w->bounties->erase(World::bea()));

        $w->bounties->award('b1', 'award-3', 'answer-2', 80, World::ana());

        self::assertSame(100, $w->points->balance(World::cy()));
        self::assertSame(0, $w->points->balance(World::bea()));
    }

    public function testAnErasedWinnerKeepsWhatWasPaidAndIsNeverPaidAgain(): void
    {
        $w = new World();
        $w->bounties->offer($w->draft(), World::ana());
        $w->bounties->claim('b1', 'answer-1', World::bea(), 'answer:901');
        $w->bounties->claim('b1', 'answer-2', World::cy(), 'answer:902');
        $w->bounties->award('b1', 'award-1', 'answer-1', 100, World::ana());
        $w->events->take();

        self::assertSame(1, $w->bounties->erase(World::bea()));

        $bounty = $w->bounties->get('b1');
        $bea = $bounty->claims[0]->claimant;
        self::assertTrue(Pseudonym::isErased($bea));
        self::assertSame($bea->toString(), $bounty->claims[0]->evidence);
        self::assertTrue($bounty->claims[1]->claimant->equals(World::cy()));
        self::assertSame('answer:902', $bounty->claims[1]->evidence);
        self::assertSame([], $w->events->take());

        $w->bounties->settle('b1');

        self::assertSame(100, $w->points->balance(World::bea()));
        self::assertSame(0, $w->points->balance($bea));
        self::assertSame(900, $w->points->balance(World::ana()));
    }

    public function testAClaimantWithoutAwardIsErasedFromASettledBountyThatStillSettles(): void
    {
        $w = new World();
        $w->bounties->offer($w->draft(), World::ana());
        $w->bounties->claim('b1', 'answer-1', World::bea(), 'answer:901');
        $w->bounties->claim('b1', 'answer-2', World::cy(), 'answer:902');
        $w->bounties->award('b1', 'award-1', 'answer-1', 100, World::ana());

        $w->bounties->erase(World::cy());
        $w->bounties->settle('b1');

        self::assertSame(100, $w->points->balance(World::bea()));
        self::assertTrue(Pseudonym::isErased($w->bounties->get('b1')->claims[1]->claimant));
    }

    public function testABountyWhoseEscrowFailedIsSettledBeforeItsWinnerIsErased(): void
    {
        $w0 = new World();
        $w = new World(escrow: new FailingEscrow($w0->points));
        $w->bounties->offer($w->draft(), World::ana());
        $w->bounties->claim('b1', 'answer-1', World::bea(), 'answer:901');

        try {
            $w->bounties->award('b1', 'award-1', 'answer-1', 100, World::ana());
            self::fail('The escrow fails once.');
        } catch (RuntimeException) {
            self::assertSame(0, $w0->points->balance(World::bea()));
        }

        $w->bounties->erase(World::bea());

        self::assertSame(100, $w0->points->balance(World::bea()));
        self::assertTrue(Pseudonym::isErased($w->bounties->get('b1')->claims[0]->claimant));
    }

    public function testEachBountyDrawsItsOwnPseudonymAndAnErasureGoesThroughBoundedBatches(): void
    {
        $store = new BusyStore();
        $w = new World($store);

        foreach (['b1', 'b2', 'b3'] as $subject => $id) {
            $w->bounties->offer($w->draft($id, subject: (string) $subject), World::ana());
            $w->bounties->claim($id, 'answer-1', World::bea(), 'answer:90'.$subject);
        }

        self::assertSame(2, $w->bounties->erase(World::bea(), 2));
        self::assertSame(2, $store->limit);
        self::assertSame(1, $w->bounties->erase(World::bea(), 0));
        self::assertSame(1, $store->limit);
        self::assertSame(0, $w->bounties->erase(World::bea(), 10_000));
        self::assertSame(Bounties::MAX_BATCH, $store->limit);
        self::assertSame([], $w->bounties->get('b1')->claims);
        self::assertSame(BountyState::Open, $w->bounties->get('b3')->state);
    }

    public function testPseudonymsDifferFromOneBountyToAnother(): void
    {
        $w = new World();
        $w->bounties->offer($w->draft('b1', amount: 10, subject: '1'), World::ana());
        $w->bounties->offer($w->draft('b2', amount: 10, subject: '2'), World::ana());

        $w->bounties->erase(World::ana());

        self::assertFalse($w->bounties->get('b1')->sponsor->equals($w->bounties->get('b2')->sponsor));
    }

    public function testAMemberNamedByNoBountyErasesNothing(): void
    {
        $w = new World();
        $bounty = $w->bounties->offer($w->draft(), World::ana());

        self::assertSame(0, $w->bounties->erase(World::dee()));
        self::assertSame(1, $w->bounties->get('b1')->version);
        self::assertSame($bounty, $bounty->leave(World::dee(), $w->clock->now()));
        self::assertSame($bounty, $bounty->pseudonymized(World::dee(), Pseudonym::draw()));

        $cancelled = $bounty->cancel(World::ana(), $w->clock->now());

        self::assertSame($cancelled, $cancelled->leave(World::ana(), $w->clock->now()));
    }

    public function testTheMemoryStoreListsTheBountiesNamingTheMemberUpToTheLimit(): void
    {
        $store = new InMemoryBountyStore();
        $w = new World($store);

        foreach (['1', 'b2', 'b3'] as $subject => $id) {
            $w->bounties->offer($w->draft($id, amount: 10, subject: (string) $subject), World::ana());
        }

        $w->bounties->claim('b2', 'answer-1', World::bea(), 'answer:901');

        self::assertSame(['1', 'b2'], $store->involving(World::ana(), 2));
        self::assertSame(['b2'], $store->involving(World::bea(), 5));
        self::assertSame([], $store->involving(World::dee(), 5));
    }
}
