<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Tests\Unit;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Symfony\Component\Clock\MockClock;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Exception\MovementConflict;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\MovementKey;
use Tilttek\Points\Points;
use Tilttek\Referrals\Contract\ReferralStore;
use Tilttek\Referrals\Exception\InvalidReferral;
use Tilttek\Referrals\Exception\ReferralConflict;
use Tilttek\Referrals\Exception\ReferralRefused;
use Tilttek\Referrals\Exception\UnknownReferralCode;
use Tilttek\Referrals\FirstAction;
use Tilttek\Referrals\Referral;
use Tilttek\Referrals\ReferralCode;
use Tilttek\Referrals\Referrals;
use Tilttek\Referrals\ReferralStatus;
use Tilttek\Referrals\Refusal;
use Tilttek\Referrals\Reward;
use Tilttek\Referrals\Store\InMemoryReferralStore;
use Tilttek\Referrals\Terms;
use Tilttek\Referrals\Tests\Unit\Fixture\Actions;
use Tilttek\Referrals\Tests\Unit\Fixture\InterleavingReferralStore;
use Tilttek\Referrals\Tests\Unit\Fixture\Joined;
use Tilttek\Referrals\Tests\Unit\Fixture\Twins;

#[CoversClass(Referrals::class)]
#[CoversClass(Reward::class)]
#[CoversClass(ReferralRefused::class)]
#[CoversClass(UnknownReferralCode::class)]
#[CoversClass(ReferralConflict::class)]
final class ReferralsTest extends TestCase
{
    private InMemoryReferralStore $store;

    private InMemoryLedger $ledger;

    private MockClock $clock;

    private Points $points;

    private Actions $actions;

    private Twins $twins;

    private Joined $joined;

    #[Override]
    protected function setUp(): void
    {
        $this->store = new InMemoryReferralStore();
        $this->ledger = new InMemoryLedger();
        $this->clock = new MockClock(new DateTimeImmutable('2026-11-20 12:00:00 UTC'));
        $this->points = new Points($this->ledger, $this->clock, new Currencies(new Currency('points'), new Currency('stars')));
        $this->actions = new Actions();
        $this->twins = new Twins();
        $this->joined = new Joined();
    }

    public function testAMemberKeepsOneCode(): void
    {
        $referrals = $this->referrals();
        $code = $referrals->code($this->member(1));

        self::assertSame(ReferralCode::LENGTH, \strlen($code->value));
        self::assertTrue($code->equals($referrals->code($this->member(1))));
        self::assertFalse($code->equals($referrals->code($this->member(2))));
        self::assertTrue($this->member(1)->equals($this->store->memberOf($code) ?? $this->member(0)));
    }

    public function testACodeTakenByAnotherMemberIsDrawnAgain(): void
    {
        $taken = ReferralCode::generate(new Randomizer(new Xoshiro256StarStar(7)));
        $this->store->addCode($this->member(9), $taken);

        $code = $this->referrals(randomizer: new Randomizer(new Xoshiro256StarStar(7)))->code($this->member(1));

        self::assertFalse($code->equals($taken));
        self::assertTrue($this->member(1)->equals($this->store->memberOf($code) ?? $this->member(0)));
    }

    public function testACodeIsDrawnUpToFiveTimes(): void
    {
        $randomizer = new Randomizer(new Xoshiro256StarStar(7));

        for ($member = 10; $member < 10 + Referrals::CODE_ATTEMPTS - 1; ++$member) {
            $this->store->addCode($this->member($member), ReferralCode::generate($randomizer));
        }

        $code = $this->referrals(randomizer: new Randomizer(new Xoshiro256StarStar(7)))->code($this->member(1));
        self::assertTrue($this->member(1)->equals($this->store->memberOf($code) ?? $this->member(0)));

        $this->store->addCode($this->member(99), ReferralCode::generate($randomizer));

        $this->expectExceptionObject(ReferralConflict::noFreeCode(5));
        $this->referrals(randomizer: new Randomizer(new Xoshiro256StarStar(7)))->code($this->member(2));
    }

    public function testACodeKeptMeanwhileByAnotherProcessIsTheMembersCode(): void
    {
        $other = ReferralCode::fromString('ZZZZZZZZZZ');
        $store = new InterleavingReferralStore($this->store, 'addCode', fn (): ?ReferralCode => $this->store->addCode($this->member(1), $other));

        self::assertTrue($other->equals($this->referrals($store)->code($this->member(1))));
    }

    public function testReferringANewcomer(): void
    {
        $code = $this->referrals()->code($this->member(1));
        $this->clock->modify('+1 hour');

        $referral = $this->referrals()->refer(strtolower($code->value), $this->member(2));

        self::assertSame(ReferralStatus::Pending, $referral->status);
        self::assertTrue($this->member(1)->equals($referral->referrer));
        self::assertTrue($this->member(2)->equals($referral->newcomer));
        self::assertSame('2026-11-20 13:00:00', $referral->referredAt->format('Y-m-d H:i:s'));
        self::assertSame($referral, $this->referrals()->referralOf($this->member(2)));
        self::assertSame([$referral], $this->referrals()->referralsOf($this->member(1)));
    }

    public function testReferringTheSameNewcomerAgainIsTheSameReferral(): void
    {
        $code = $this->referrals()->code($this->member(1));
        $referral = $this->referrals()->refer($code->value, $this->member(2));
        $this->joined->dates = [];

        self::assertSame($referral, $this->referrals(terms: new Terms(newcomerWindow: 'P1D'))->refer($code->value, $this->member(2)));
    }

    public function testANewcomerIsReferredByOneMemberOnly(): void
    {
        $this->referrals()->refer($this->referrals()->code($this->member(1))->value, $this->member(3));

        $this->assertRefused(Refusal::AlreadyReferred, fn (): Referral => $this->referrals()->refer($this->referrals()->code($this->member(2))->value, $this->member(3)));
    }

    public function testAReferralAddedMeanwhileByAnotherMemberIsRefused(): void
    {
        $first = $this->referrals()->code($this->member(1))->value;
        $second = $this->referrals()->code($this->member(2))->value;
        $store = new InterleavingReferralStore($this->store, 'ofNewcomer', fn (): Referral => $this->referrals()->refer($first, $this->member(3)));

        $this->assertRefused(Refusal::AlreadyReferred, fn (): Referral => $this->referrals($store)->refer($second, $this->member(3)));
        self::assertTrue($this->member(1)->equals($this->store->ofNewcomer($this->member(3))->referrer ?? $this->member(0)));
    }

    public function testAnUnknownCodeIsRefusedWithoutRepeatingIt(): void
    {
        $this->expectExceptionObject(UnknownReferralCode::create());
        $this->referrals()->refer('ABCDEFGHJK', $this->member(2));
    }

    public function testATextThatIsNoCodeIsInvalid(): void
    {
        $this->expectException(InvalidReferral::class);
        $this->referrals()->refer('nope', $this->member(2));
    }

    public function testAMemberCannotReferThemselves(): void
    {
        $code = $this->referrals()->code($this->member(1))->value;

        $this->assertRefused(Refusal::SelfReferral, fn (): Referral => $this->referrals()->refer($code, $this->member(1)));
    }

    public function testAMemberCannotReferTheirOtherAccount(): void
    {
        $code = $this->referrals()->code($this->member(1))->value;
        $this->twins->pairs = [['member:1', 'member:2']];

        $this->assertRefused(Refusal::SelfReferral, fn (): Referral => $this->referrals()->refer($code, $this->member(2)));
        self::assertNotNull($this->referrals()->refer($code, $this->member(3)));
        self::assertNull($this->store->ofNewcomer($this->member(2)));
    }

    public function testAReferrerWaitsForTheSeniority(): void
    {
        $code = $this->referrals()->code($this->member(1))->value;
        $terms = new Terms(referrerSeniority: 'P7D');

        $this->assertRefused(Refusal::NewMember, fn (): Referral => $this->referrals(terms: $terms)->refer($code, $this->member(2)));

        $this->joined->dates['member:1'] = new DateTimeImmutable('2026-11-13 12:00:01 UTC');
        $this->assertRefused(Refusal::NewMember, fn (): Referral => $this->referrals(terms: $terms)->refer($code, $this->member(2)));

        $this->joined->dates['member:1'] = new DateTimeImmutable('2026-11-13 12:00:00 UTC');
        self::assertSame(ReferralStatus::Pending, $this->referrals(terms: $terms)->refer($code, $this->member(2))->status);
    }

    public function testOnlyANewcomerCanBeReferred(): void
    {
        $code = $this->referrals()->code($this->member(1))->value;
        $terms = new Terms(newcomerWindow: 'P1D');

        $this->assertRefused(Refusal::NotNewcomer, fn (): Referral => $this->referrals(terms: $terms)->refer($code, $this->member(2)));

        $this->joined->dates['member:2'] = new DateTimeImmutable('2026-11-19 11:59:59 UTC');
        $this->assertRefused(Refusal::NotNewcomer, fn (): Referral => $this->referrals(terms: $terms)->refer($code, $this->member(2)));

        $this->joined->dates['member:2'] = new DateTimeImmutable('2026-11-19 12:00:00 UTC');
        self::assertSame(ReferralStatus::Pending, $this->referrals(terms: $terms)->refer($code, $this->member(2))->status);
    }

    public function testTheRewardWaitsForARealFirstAction(): void
    {
        $this->refer(1, 2);

        $this->assertRefused(Refusal::NoFirstAction, fn (): ?Reward => $this->referrals()->reward($this->member(2)));
        self::assertSame(0, $this->points->balance($this->member(2)));
        self::assertSame(ReferralStatus::Pending, $this->store->ofNewcomer($this->member(2))?->status);
    }

    public function testAnActionBeforeTheReferralDoesNotCount(): void
    {
        $this->refer(1, 2);
        $this->actions->actions['member:2'] = new FirstAction('game.played', new DateTimeImmutable('2026-11-20 11:59:59 UTC'));

        $this->assertRefused(Refusal::NoFirstAction, fn (): ?Reward => $this->referrals()->reward($this->member(2)));

        $this->actions->actions['member:2'] = new FirstAction('game.played', new DateTimeImmutable('2026-11-20 12:00:00 UTC'));
        self::assertNotNull($this->referrals()->reward($this->member(2)));
    }

    public function testRewardingBothSides(): void
    {
        $referral = $this->refer(1, 2);
        $this->act(2);
        $this->clock->modify('+1 day');

        $reward = $this->referrals()->reward($this->member(2));

        self::assertNotNull($reward);
        self::assertTrue($reward->new);
        self::assertSame(100, $reward->referrerPoints);
        self::assertSame(50, $reward->newcomerPoints);
        self::assertSame(ReferralStatus::Rewarded, $reward->referral->status);
        self::assertSame('2026-11-21 12:00:00', $reward->referral->settledAt?->format('Y-m-d H:i:s'));
        self::assertSame(100, $this->points->balance($this->member(1)));
        self::assertSame(50, $this->points->balance($this->member(2)));

        $movement = $this->ledger->find(MovementKey::fromString('referral:'.$referral->id->value.':referrer'))?->movement;
        self::assertSame('issuance:referrals', $movement?->debit->toString());
        self::assertSame('member:member:1', $movement->credit->toString());
        self::assertSame('member:member:2', $this->ledger->find(MovementKey::fromString('referral:'.$referral->id->value.':newcomer'))?->movement->credit->toString());
    }

    public function testTheTermsNameTheCurrencyAndTheSource(): void
    {
        $this->refer(1, 2);
        $this->act(2);

        $this->referrals(terms: new Terms(30, 20, currency: 'stars', source: 'campaign:friends'))->reward($this->member(2));

        self::assertSame(30, $this->points->balance($this->member(1), 'stars'));
        self::assertSame(20, $this->points->balance($this->member(2), 'stars'));
        self::assertSame(0, $this->points->balance($this->member(1)));
        self::assertSame('issuance:campaign:friends', $this->points->history($this->member(1), 'stars')[0]->movement->debit->toString());
    }

    public function testARewardAskedAgainGivesNothingMore(): void
    {
        $this->refer(1, 2);
        $this->act(2);
        $first = $this->referrals()->reward($this->member(2));

        $again = $this->referrals(terms: new Terms(999, 999))->reward($this->member(2));

        self::assertNotNull($again);
        self::assertFalse($again->new);
        self::assertSame(100, $again->referrerPoints);
        self::assertSame(50, $again->newcomerPoints);
        self::assertNotNull($first);
        self::assertTrue($first->referral->id->equals($again->referral->id));
        self::assertSame($first->referral->settledAt?->format(\DATE_ATOM), $again->referral->settledAt?->format(\DATE_ATOM));
        self::assertSame(100, $this->points->balance($this->member(1)));
        self::assertSame(50, $this->points->balance($this->member(2)));
    }

    public function testTwoProcessesRewardingAtOnceGiveThePointsOnce(): void
    {
        $this->refer(1, 2);
        $this->act(2);
        $other = null;
        $store = new InterleavingReferralStore($this->store, 'ofNewcomer', function () use (&$other): void {
            $other = $this->referrals()->reward($this->member(2));
        });

        $reward = $this->referrals($store)->reward($this->member(2));

        self::assertTrue($other?->new);
        self::assertFalse($reward?->new);
        self::assertSame(100, $this->points->balance($this->member(1)));
        self::assertSame(50, $this->points->balance($this->member(2)));
        self::assertCount(1, $this->points->history($this->member(1)));
        self::assertCount(1, $this->points->history($this->member(2)));
    }

    public function testAPaymentCutShortIsCompletedOnTheNextCall(): void
    {
        $referral = $this->refer(1, 2);
        $this->act(2);
        $this->store->settle($referral->id, 100, 50, 10, $this->clock->now());

        $reward = $this->referrals()->reward($this->member(2));

        self::assertTrue($reward?->new);
        self::assertSame(100, $this->points->balance($this->member(1)));
        self::assertSame(50, $this->points->balance($this->member(2)));
    }

    public function testTheReferrerIsRewardedUpToTheCap(): void
    {
        $terms = new Terms(maxRewards: 2);
        $remaining = [];

        foreach ([2, 3, 4] as $newcomer) {
            $this->refer(1, $newcomer);
            $this->act($newcomer);
            $remaining[] = $this->referrals(terms: $terms)->remaining($this->member(1));
            $this->referrals(terms: $terms)->reward($this->member($newcomer));
        }

        self::assertSame([2, 1, 0], $remaining);

        self::assertSame(200, $this->points->balance($this->member(1)));
        self::assertSame(50, $this->points->balance($this->member(4)));
        self::assertSame(0, $this->store->ofNewcomer($this->member(4))?->referrerPoints);
        self::assertSame(0, $this->referrals(terms: $terms)->remaining($this->member(1)));
        self::assertSame(10, $this->referrals()->remaining($this->member(9)));
    }

    public function testTheCapHoldsWhenTwoNewcomersActAtOnce(): void
    {
        $terms = new Terms(maxRewards: 1);
        $this->refer(1, 2);
        $this->refer(1, 3);
        $this->act(2);
        $this->act(3);
        $store = new InterleavingReferralStore($this->store, 'ofNewcomer', fn (): ?Reward => $this->referrals(terms: $terms)->reward($this->member(3)));

        $reward = $this->referrals($store, terms: $terms)->reward($this->member(2));

        self::assertSame(0, $reward?->referrerPoints);
        self::assertSame(100, $this->points->balance($this->member(1)));
        self::assertSame(50, $this->points->balance($this->member(2)));
        self::assertSame(50, $this->points->balance($this->member(3)));
    }

    public function testAReferralOfTheSamePersonFoundLaterIsRefused(): void
    {
        $this->refer(1, 2);
        $this->act(2);
        $this->twins->pairs = [['member:1', 'member:2']];

        $this->assertRefused(Refusal::SelfReferral, fn (): ?Reward => $this->referrals()->reward($this->member(2)));
        self::assertSame(ReferralStatus::Refused, $this->store->ofNewcomer($this->member(2))?->status);

        $this->twins->pairs = [];
        $this->assertRefused(Refusal::SelfReferral, fn (): ?Reward => $this->referrals()->reward($this->member(2)));
        self::assertSame(0, $this->points->balance($this->member(1)));
        self::assertSame(0, $this->points->balance($this->member(2)));
    }

    public function testAMemberNotReferredHasNoReward(): void
    {
        self::assertNull($this->referrals()->reward($this->member(2)));
    }

    public function testAReferralErasedDuringTheRewardHasNoReward(): void
    {
        $this->refer(1, 2);
        $this->act(2);
        $store = new InterleavingReferralStore($this->store, 'ofNewcomer', fn (): mixed => $this->store->eraseMember($this->member(2)));

        self::assertNull($this->referrals($store)->reward($this->member(2)));
        self::assertSame(0, $this->points->balance($this->member(1)));
    }

    public function testAKeyWrittenForOtherPointsIsAConflict(): void
    {
        $referral = $this->refer(1, 2);
        $this->act(2);
        $this->points->grant($this->member(2), 7, 'referral:'.$referral->id->value.':newcomer');

        $this->expectException(MovementConflict::class);
        $this->referrals()->reward($this->member(2));
    }

    public function testReadingTheReferralsOfAMember(): void
    {
        $made = [$this->refer(1, 2), $this->refer(1, 3), $this->refer(1, 4)];

        self::assertSame([$made[1], $made[2]], $this->referrals()->referralsOf($this->member(1), 1, 100));
        self::assertSame([$made[0]], $this->referrals()->referralsOf($this->member(1), 0, 1));
        self::assertCount(3, $this->referrals()->referralsOf($this->member(1)));
        self::assertSame([], $this->referrals()->referralsOf($this->member(2)));

        foreach ([[-1, 1], [0, 0], [0, 101]] as [$offset, $limit]) {
            try {
                $this->referrals()->referralsOf($this->member(1), $offset, $limit);
                self::fail(\sprintf('%d, %d', $offset, $limit));
            } catch (InvalidReferral $invalid) {
                self::assertSame('A read starts at 0 or later and holds 1 to 100 referrals.', $invalid->getMessage());
            }
        }
    }

    public function testAReadHoldsFiftyReferralsByDefault(): void
    {
        for ($newcomer = 2; $newcomer <= 52; ++$newcomer) {
            $this->refer(1, $newcomer);
        }

        self::assertCount(50, $this->referrals()->referralsOf($this->member(1)));
    }

    public function testNothingRemainsBelowZeroWhenTheCapWasLowered(): void
    {
        foreach ([2, 3] as $newcomer) {
            $this->refer(1, $newcomer);
            $this->act($newcomer);
            $this->referrals(terms: new Terms(maxRewards: 2))->reward($this->member($newcomer));
        }

        self::assertSame(0, $this->referrals(terms: new Terms(maxRewards: 1))->remaining($this->member(1)));
    }

    public function testEachRefusalHasItsOwnMessage(): void
    {
        $messages = [
            Refusal::SelfReferral->value => 'A member cannot refer themselves.',
            Refusal::NewMember->value => 'The referrer has not been a member long enough to refer others.',
            Refusal::NotNewcomer->value => 'Only a member who has just joined can be referred.',
            Refusal::AlreadyReferred->value => 'This member was already referred by another member.',
            Refusal::NoFirstAction->value => 'The referred member has not made a first real action since the referral.',
        ];

        foreach (Refusal::cases() as $refusal) {
            self::assertSame($messages[$refusal->value], ReferralRefused::because($refusal)->getMessage());
        }
    }

    public function testErasingTheNewcomerLeavesNoLink(): void
    {
        $this->refer(1, 2);
        $this->refer(1, 3);
        $this->act(3);
        $this->referrals()->reward($this->member(3));

        $erasure = $this->referrals()->erase($this->member(3));

        self::assertSame([0, 0, 1], [$erasure->codes, $erasure->deleted, $erasure->detached]);
        self::assertNull($this->referrals()->referralOf($this->member(3)));
        self::assertSame(['member:2', 'erased:member'], array_map(static fn (Referral $referral): string => $referral->newcomer->toString(), $this->referrals()->referralsOf($this->member(1))));
        self::assertSame(9, $this->referrals()->remaining($this->member(1)));
        self::assertNull($this->referrals()->reward($this->member(3)));

        $erasure = $this->referrals()->erase($this->member(2));
        self::assertSame([0, 1, 0], [$erasure->codes, $erasure->deleted, $erasure->detached]);
        self::assertCount(1, $this->referrals()->referralsOf($this->member(1)));
    }

    public function testErasingTheReferrerStillRewardsTheNewcomer(): void
    {
        $code = $this->referrals()->code($this->member(1));
        $this->referrals()->refer($code->value, $this->member(2));
        $this->act(2);

        $erasure = $this->referrals()->erase($this->member(1));

        self::assertSame([1, 0, 1], [$erasure->codes, $erasure->deleted, $erasure->detached]);
        self::assertNull($this->store->memberOf($code));
        self::assertSame([], $this->referrals()->referralsOf($this->member(1)));

        $reward = $this->referrals()->reward($this->member(2));
        self::assertSame(0, $reward?->referrerPoints);
        self::assertTrue($reward->new);
        self::assertSame(50, $reward->newcomerPoints);
        self::assertSame('erased:member', $reward->referral->referrer->toString());
        self::assertSame(50, $this->points->balance($this->member(2)));
    }

    public function testARewardedReferrerErasedIsNotPaidAgain(): void
    {
        $this->refer(1, 2);
        $this->act(2);
        $this->referrals()->reward($this->member(2));
        $this->referrals()->erase($this->member(1));
        $this->ledger->eraseMember($this->member(1));

        $reward = $this->referrals()->reward($this->member(2));

        self::assertFalse($reward?->new);
        self::assertSame(100, $reward->referrerPoints);
        self::assertSame(0, $this->points->balance($this->member(1)));
    }

    private function referrals(?ReferralStore $store = null, ?Terms $terms = null, ?Randomizer $randomizer = null): Referrals
    {
        return new Referrals($store ?? $this->store, $this->points, $this->actions, $this->clock, $terms ?? new Terms(), $this->joined, $this->twins, $randomizer ?? new Randomizer());
    }

    private function refer(int $referrer, int $newcomer): Referral
    {
        return $this->referrals()->refer($this->referrals()->code($this->member($referrer))->value, $this->member($newcomer));
    }

    private function act(int $member): void
    {
        $this->actions->actions['member:'.$member] = new FirstAction('game.played', $this->clock->now());
    }

    private function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
    }

    /**
     * @param callable(): mixed $call
     */
    private function assertRefused(Refusal $refusal, callable $call): void
    {
        try {
            $call();
            self::fail(\sprintf('The call was not refused for "%s".', $refusal->value));
        } catch (ReferralRefused $referralRefused) {
            self::assertSame($refusal, $referralRefused->refusal);
            self::assertSame(ReferralRefused::because($refusal)->getMessage(), $referralRefused->getMessage());
        }
    }
}
