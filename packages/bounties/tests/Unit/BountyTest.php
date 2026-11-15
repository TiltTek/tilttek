<?php

declare(strict_types=1);

namespace Tilttek\Bounties\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Bounties\Award;
use Tilttek\Bounties\Bounty;
use Tilttek\Bounties\BountyDraft;
use Tilttek\Bounties\BountyReason;
use Tilttek\Bounties\BountyRules;
use Tilttek\Bounties\BountyState;
use Tilttek\Bounties\Claim;
use Tilttek\Bounties\Exception\BountyRefused;
use Tilttek\Bounties\Exception\InvalidBounty;
use Tilttek\Bounties\Payout;
use Tilttek\Bounties\Prize;
use Tilttek\Bounties\Tests\Unit\Fixture\World;

#[CoversClass(Bounty::class)]
#[CoversClass(Claim::class)]
#[CoversClass(Award::class)]
#[CoversClass(BountyState::class)]
#[CoversClass(BountyRefused::class)]
final class BountyTest extends TestCase
{
    public function testAnOfferedBountyIsOpenAtVersionOneInUtc(): void
    {
        $bounty = self::bounty();

        self::assertSame(BountyState::Open, $bounty->state);
        self::assertFalse($bounty->state->isSettled());
        self::assertSame(1, $bounty->version);
        self::assertSame('2026-11-14T01:00:00.000000Z', $bounty->toArray()['opened_at']);
        self::assertSame('2026-11-21T01:00:00.000000Z', $bounty->toArray()['ends_at']);
        self::assertSame('2026-11-22T01:00:00.000000Z', $bounty->toArray()['grace_ends_at']);
        self::assertSame('question:42', $bounty->toArray()['subject']);
        self::assertSame('detail', $bounty->toArray()['reason']);
        self::assertSame(0, $bounty->awarded());
        self::assertSame([], $bounty->payouts());
        self::assertNull($bounty->settledAt);
    }

    public function testTheLengthOfABountyIsWithinTheRules(): void
    {
        $rules = new BountyRules(shortest: 3600, longest: 7200);

        self::assertSame(1, self::bounty(ends: '+3600 seconds', rules: $rules)->version);
        self::assertSame(1, self::bounty(ends: '+7200 seconds', rules: $rules)->version);

        foreach (['+3599 seconds', '+7201 seconds', '-1 hour'] as $ends) {
            try {
                self::bounty(ends: $ends, rules: $rules);
                self::fail(\sprintf('A bounty ending %s is outside the rules.', $ends));
            } catch (InvalidBounty $invalidBounty) {
                self::assertStringContainsString('lasts 3600 to 7200 seconds', $invalidBounty->getMessage());
            }
        }
    }

    public function testTheAmountIsWithinTheRules(): void
    {
        $rules = new BountyRules(smallestPrize: 10, largestPrize: 20);

        self::assertSame(10, self::bounty(amount: 10, rules: $rules)->prize->amount);
        self::assertSame(20, self::bounty(amount: 20, rules: $rules)->prize->amount);

        foreach ([9, 21] as $amount) {
            try {
                self::bounty(amount: $amount, rules: $rules);
                self::fail(\sprintf('%d points are outside the rules.', $amount));
            } catch (InvalidBounty $invalidBounty) {
                self::assertStringContainsString('A bounty is 10 to 20 points', $invalidBounty->getMessage());
            }
        }
    }

    public function testADraftMatchesOnlyTheSameBounty(): void
    {
        $bounty = self::bounty();
        $at = self::at();

        self::assertTrue($bounty->matches(self::draft(), World::ana()));
        self::assertTrue($bounty->matches(new BountyDraft('b1', World::question(), BountyReason::Detail, new Prize(100), $at->modify('+7 days')->setTimezone(new DateTimeZone('Asia/Tokyo'))), World::ana()));
        self::assertFalse($bounty->matches(self::draft(), World::bea()));
        self::assertFalse($bounty->matches(self::draft(id: 'b2'), World::ana()));
        self::assertFalse($bounty->matches(self::draft(amount: 99), World::ana()));
        self::assertFalse($bounty->matches(self::draft(ends: '+6 days'), World::ana()));
        self::assertFalse($bounty->matches(new BountyDraft('b1', World::question('43'), BountyReason::Detail, new Prize(100), $at->modify('+7 days')), World::ana()));
        self::assertFalse($bounty->matches(new BountyDraft('b1', World::question(), BountyReason::Other, new Prize(100), $at->modify('+7 days')), World::ana()));
    }

    public function testClaimsAreMadeUntilTheEndByAnyoneButTheSponsor(): void
    {
        $bounty = self::bounty();

        $claimed = $bounty->claimWith('c1', World::bea(), 'answer:1', $bounty->endsAt->modify('-1 second'));
        self::assertSame(2, $claimed->version);
        self::assertSame('2026-11-21T00:59:59.000000Z', $claimed->toArray()['claims'][0]['claimed_at']);
        self::assertSame($claimed, $claimed->claimWith('c1', World::bea(), 'answer:1', $bounty->endsAt->modify('+1 year')));

        foreach ([
            static fn (): Bounty => $bounty->claimWith('c1', World::bea(), 'answer:1', $bounty->endsAt),
            static fn (): Bounty => $bounty->claimWith('c1', World::ana(), 'answer:1', self::at()),
            static fn (): Bounty => $claimed->claimWith('c1', World::bea(), 'answer:2', self::at()),
            static fn (): Bounty => $claimed->claimWith('c1', World::cy(), 'answer:1', self::at()),
            static fn (): Bounty => $bounty->cancel(World::ana(), self::at())->claimWith('c1', World::bea(), 'answer:1', self::at()),
        ] as $n => $move) {
            try {
                $move();
                self::fail(\sprintf('Move %d is refused.', $n));
            } catch (BountyRefused) {
                self::assertSame([], $bounty->claims);
            }
        }
    }

    public function testAMemberClaimsFiveTimesAtMost(): void
    {
        $bounty = self::bounty();

        for ($n = 1; $n <= Bounty::MAX_CLAIMS_PER_MEMBER; ++$n) {
            $bounty = $bounty->claimWith('c'.$n, World::bea(), 'answer:'.$n, self::at());
        }

        self::assertSame(7, $bounty->claimWith('d1', World::cy(), 'answer:d', self::at())->version);

        $this->expectException(BountyRefused::class);
        $this->expectExceptionMessageIsOrContains('holds 5 claims of member:bea');

        $bounty->claimWith('c6', World::bea(), 'answer:6', self::at());
    }

    public function testABountyTakesTwoHundredClaims(): void
    {
        $bounty = self::bounty();

        for ($n = 0; $n < Bounty::MAX_CLAIMS; ++$n) {
            $bounty = $bounty->claimWith('c'.$n, ResourceReference::of('member', 'm'.intdiv($n, 5)), 'answer:'.$n, self::at());
        }

        $this->expectException(BountyRefused::class);
        $this->expectExceptionMessageIsOrContains('holds 200 claims');

        $bounty->claimWith('extra', World::bea(), 'answer:x', self::at());
    }

    public function testTheSponsorAwardsUntilTheGraceEnds(): void
    {
        $bounty = self::claimed();

        $awarded = $bounty->awardTo('a1', 'c1', 40, World::ana(), $bounty->graceEndsAt->modify('-1 second'));
        self::assertSame(BountyState::Open, $awarded->state);
        self::assertSame(40, $awarded->awarded());
        self::assertSame('2026-11-22T00:59:59.000000Z', $awarded->toArray()['awards'][0]['awarded_at']);
        self::assertSame($awarded, $awarded->awardTo('a1', 'c1', 40, World::ana(), $bounty->graceEndsAt->modify('+1 year')));

        $this->expectException(BountyRefused::class);
        $this->expectExceptionMessageIsOrContains('The time to award on the bounty "b1" is over.');

        $bounty->awardTo('a1', 'c1', 40, World::ana(), $bounty->graceEndsAt);
    }

    public function testTheWholeAmountAwardedSettlesTheBounty(): void
    {
        $bounty = self::claimed()->awardTo('a1', 'c1', 60, World::ana(), self::at());

        $settled = $bounty->awardTo('a2', 'c2', 40, World::ana(), self::at());

        self::assertSame(BountyState::Awarded, $settled->state);
        self::assertSame('2026-11-14T01:00:00.000000Z', $settled->toArray()['settled_at']);
        self::assertEquals([new Payout(World::bea(), 60), new Payout(World::cy(), 40)], $settled->payouts());

        $this->expectException(BountyRefused::class);
        $this->expectExceptionMessageIsOrContains('is awarded: it is not awarded');

        $settled->awardTo('a3', 'c1', 1, World::ana(), self::at());
    }

    public function testAwardsAreNoMoreThanWhatIsLeft(): void
    {
        $bounty = self::claimed()->awardTo('a1', 'c1', 60, World::ana(), self::at());

        $this->expectException(BountyRefused::class);
        $this->expectExceptionMessageIsOrContains('has 40 points left to award; 41 is too many');

        $bounty->awardTo('a2', 'c2', 41, World::ana(), self::at());
    }

    public function testAwardsAreTheSponsorsToClaimsOfTheBounty(): void
    {
        $bounty = self::claimed();
        $awarded = $bounty->awardTo('a1', 'c1', 10, World::ana(), self::at());

        foreach ([
            static fn (): Bounty => $bounty->awardTo('a1', 'c1', 10, World::bea(), self::at()),
            static fn (): Bounty => $bounty->awardTo('a1', 'c9', 10, World::ana(), self::at()),
            static fn (): Bounty => $awarded->awardTo('a1', 'c2', 10, World::ana(), self::at()),
            static fn (): Bounty => $awarded->awardTo('a1', 'c1', 11, World::ana(), self::at()),
        ] as $n => $move) {
            try {
                $move();
                self::fail(\sprintf('Move %d is refused.', $n));
            } catch (BountyRefused) {
                self::assertSame([], $bounty->awards);
            }
        }
    }

    public function testABountyTakesTwentyAwards(): void
    {
        $bounty = self::claimed(amount: 1000);

        for ($n = 0; $n < Bounty::MAX_AWARDS; ++$n) {
            $bounty = $bounty->awardTo('a'.$n, 'c1', 1, World::ana(), self::at());
        }

        self::assertEquals([new Payout(World::bea(), 20)], $bounty->payouts());

        $this->expectException(BountyRefused::class);
        $this->expectExceptionMessageIsOrContains('holds 20 awards');

        $bounty->awardTo('extra', 'c1', 1, World::ana(), self::at());
    }

    public function testTheSponsorClosesWhatItAwarded(): void
    {
        $bounty = self::claimed();
        $closed = $bounty->awardTo('a1', 'c1', 10, World::ana(), self::at())->close(World::ana(), self::at());

        self::assertSame(BountyState::Awarded, $closed->state);
        self::assertSame($closed, $closed->close(World::ana(), self::at()));

        foreach ([
            static fn (): Bounty => $bounty->close(World::ana(), self::at()),
            static fn (): Bounty => $closed->close(World::bea(), self::at()),
            static fn (): Bounty => $bounty->expire($bounty->graceEndsAt)->close(World::ana(), self::at()),
        ] as $n => $move) {
            try {
                $move();
                self::fail(\sprintf('Move %d is refused.', $n));
            } catch (BountyRefused) {
                self::assertSame(BountyState::Open, $bounty->state);
            }
        }
    }

    public function testAnAwardRaisesTheVersionByOne(): void
    {
        $bounty = self::claimed();

        self::assertSame($bounty->version + 1, $bounty->awardTo('a1', 'c1', 10, World::ana(), self::at())->version);
    }

    public function testASettledBountyWithoutAwardsCannotBeClosed(): void
    {
        $expired = self::claimed()->expire(self::at()->modify('+30 days'));

        $this->expectException(BountyRefused::class);
        $this->expectExceptionMessageIsOrContains('it cannot be closed');

        $expired->close(World::ana(), self::at());
    }

    public function testTheSponsorCancelsBeforeAnyClaim(): void
    {
        $bounty = self::bounty();

        $cancelled = $bounty->cancel(World::ana(), self::at());
        self::assertSame(BountyState::Cancelled, $cancelled->state);
        self::assertSame($cancelled, $cancelled->cancel(World::ana(), self::at()));

        foreach ([
            static fn (): Bounty => $bounty->cancel(World::bea(), self::at()),
            static fn (): Bounty => self::claimed()->cancel(World::ana(), self::at()),
            static fn (): Bounty => $bounty->expire($bounty->graceEndsAt)->cancel(World::ana(), self::at()),
        ] as $n => $move) {
            try {
                $move();
                self::fail(\sprintf('Move %d is refused.', $n));
            } catch (BountyRefused) {
                self::assertSame(BountyState::Open, $bounty->state);
            }
        }
    }

    public function testABountyIsDueFromTheEndOfItsGrace(): void
    {
        $bounty = self::claimed();
        $graceEnds = $bounty->graceEndsAt;

        self::assertFalse($bounty->isDue($graceEnds->modify('-1 second')));
        self::assertTrue($bounty->isDue($graceEnds));

        $expired = $bounty->expire($graceEnds);
        self::assertSame(BountyState::Expired, $expired->state);
        self::assertFalse($expired->isDue($graceEnds));
        self::assertSame($expired, $expired->expire($graceEnds));
        self::assertSame(BountyState::Awarded, $bounty->awardTo('a1', 'c1', 5, World::ana(), self::at())->expire($graceEnds)->state);

        $this->expectException(BountyRefused::class);
        $this->expectExceptionMessageIsOrContains('still open');

        $bounty->expire($graceEnds->modify('-1 second'));
    }

    public function testClaimsAndAwardsAreFoundByTheirIdentifier(): void
    {
        $bounty = self::claimed()->awardTo('a1', 'c2', 5, World::ana(), self::at())->awardTo('a2', 'c1', 6, World::ana(), self::at());

        self::assertSame('answer:2', $bounty->claim('c2')?->evidence);
        self::assertNull($bounty->claim('c3'));
        self::assertSame(6, $bounty->award('a2')?->amount);
        self::assertNull($bounty->award('a3'));
        self::assertSame(11, $bounty->awarded());
        self::assertEquals([new Payout(World::cy(), 5), new Payout(World::bea(), 6)], $bounty->payouts());
    }

    private static function claimed(int $amount = 100): Bounty
    {
        return self::bounty(amount: $amount)->claimWith('c1', World::bea(), 'answer:1', self::at())->claimWith('c2', World::cy(), 'answer:2', self::at());
    }

    private static function bounty(string $ends = '+7 days', int $amount = 100, BountyRules $rules = new BountyRules()): Bounty
    {
        return Bounty::offer(self::draft('b1', $ends, $amount), World::ana(), self::at(), $rules);
    }

    private static function draft(string $id = 'b1', string $ends = '+7 days', int $amount = 100): BountyDraft
    {
        return new BountyDraft($id, World::question(), BountyReason::Detail, new Prize($amount), self::at()->modify($ends));
    }

    private static function at(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-13 20:00:00 America/Toronto');
    }
}
