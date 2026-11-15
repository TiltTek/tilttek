<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Bounties\Bounties;
use Tilttek\Bounties\BountyRules;
use Tilttek\Bounties\Exception\BountyRefused;
use Tilttek\Bounties\Exception\InvalidBounty;
use Tilttek\Bounties\Store\InMemoryBountyStore;
use Tilttek\Bounties\Tests\Unit\Fixture\World;

#[CoversClass(Bounties::class)]
#[CoversClass(BountyRules::class)]
#[CoversClass(BountyRefused::class)]
#[CoversClass(InMemoryBountyStore::class)]
final class SponsorLimitTest extends TestCase
{
    public function testAMemberSponsorsAsManyOpenBountiesAsTheSiteAllows(): void
    {
        $w = new World(rules: new BountyRules(openPerSponsor: 2));
        $w->bounties->offer($w->draft('b1', subject: '1'), World::ana());
        $w->bounties->offer($w->draft('b2', subject: '2'), World::ana());

        try {
            $w->bounties->offer($w->draft('b3', subject: '3'), World::ana());
            self::fail('Ana sponsors two open bounties already.');
        } catch (BountyRefused $bountyRefused) {
            self::assertSame('member:ana sponsors 2 open bounties, the most the site allows at once.', $bountyRefused->getMessage());
        }

        self::assertSame(800, $w->points->balance(World::ana()));
        self::assertNull($w->store->get('b3'));
    }

    public function testASettledBountyFreesItsPlace(): void
    {
        $w = new World(rules: new BountyRules(openPerSponsor: 1));
        $w->bounties->offer($w->draft('b1', subject: '1'), World::ana());
        $w->bounties->cancel('b1', World::ana());

        $w->bounties->offer($w->draft('b2', subject: '2'), World::ana());

        self::assertSame(900, $w->points->balance(World::ana()));
    }

    public function testAnOfferSentAgainIsNotCountedAgainstItsSponsor(): void
    {
        $w = new World(rules: new BountyRules(openPerSponsor: 1));
        $first = $w->bounties->offer($w->draft(), World::ana());

        self::assertSame($first->toArray(), $w->bounties->offer($w->draft(), World::ana())->toArray());
    }

    public function testTheMemoryStoreCountsTheOpenBountiesOfEachSponsor(): void
    {
        $store = new InMemoryBountyStore();
        $w = new World($store);
        $w->points->fund(World::bea(), 100);
        $w->bounties->offer($w->draft('b1', subject: '1'), World::ana());
        $w->bounties->offer($w->draft('b2', subject: '2'), World::ana());
        $w->bounties->offer($w->draft('b3', subject: '3'), World::bea());
        $w->bounties->cancel('b2', World::ana());

        self::assertSame(1, $store->openBy(World::ana()));
        self::assertSame(1, $store->openBy(World::bea()));
        self::assertSame(0, $store->openBy(World::cy()));
    }

    public function testTheLimitOfTheRulesSaysItsBounds(): void
    {
        $this->expectException(InvalidBounty::class);
        $this->expectExceptionMessageIsOrContains('A member sponsors 1 to 100 open bounties at once; 0 is not that.');

        new BountyRules(openPerSponsor: 0);
    }
}
