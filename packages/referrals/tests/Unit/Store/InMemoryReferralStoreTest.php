<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Tests\Unit\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Referrals\Erasure;
use Tilttek\Referrals\Referral;
use Tilttek\Referrals\ReferralCode;
use Tilttek\Referrals\ReferralId;
use Tilttek\Referrals\ReferralStatus;
use Tilttek\Referrals\Store\InMemoryReferralStore;

#[CoversClass(InMemoryReferralStore::class)]
final class InMemoryReferralStoreTest extends TestCase
{
    public function testCodesAreUniqueOnBothSides(): void
    {
        $store = new InMemoryReferralStore();
        $code = ReferralCode::fromString('AAAAAAAA');

        self::assertSame($code, $store->addCode($this->member(1), $code));
        self::assertSame($code, $store->addCode($this->member(1), ReferralCode::fromString('BBBBBBBB')));
        self::assertNull($store->addCode($this->member(2), ReferralCode::fromString('AAAAAAAA')));
        self::assertNull($store->codeOf($this->member(2)));
        self::assertNull($store->memberOf(ReferralCode::fromString('BBBBBBBB')));
        self::assertSame('member:1', $store->memberOf(ReferralCode::fromString('AAAAAAAA'))?->toString());
    }

    public function testANewcomerIsReferredOnce(): void
    {
        $store = new InMemoryReferralStore();
        $first = $this->referral('a', 1, 3);

        self::assertSame($first, $store->add($first));
        self::assertSame($first, $store->add($this->referral('b', 2, 3)));
        self::assertSame([], $store->ofReferrer($this->member(2), 0, 10));
        self::assertNull($store->settle(ReferralId::fromString(str_repeat('b', 32)), 1, 1, 1, new DateTimeImmutable()));
        self::assertNull($store->refuse(ReferralId::fromString(str_repeat('b', 32)), new DateTimeImmutable()));
    }

    public function testSettlingOnceWithinTheCap(): void
    {
        $store = new InMemoryReferralStore();
        $store->add($this->referral('a', 1, 2));
        $store->add($this->referral('b', 1, 3));
        $store->add($this->referral('c', 1, 4));

        $at = new DateTimeImmutable('2026-11-20 12:00:00 UTC');

        $settled = $store->settle(ReferralId::fromString(str_repeat('a', 32)), 100, 50, 1, $at);
        self::assertSame([ReferralStatus::Rewarded, 100, 50], [$settled?->status, $settled?->referrerPoints, $settled?->newcomerPoints]);
        self::assertSame($settled, $store->settle(ReferralId::fromString(str_repeat('a', 32)), 7, 7, 9, $at));
        self::assertSame($settled, $store->refuse(ReferralId::fromString(str_repeat('a', 32)), $at));
        self::assertSame(1, $store->rewardedCount($this->member(1)));

        $capped = $store->settle(ReferralId::fromString(str_repeat('b', 32)), 100, 50, 1, $at);
        self::assertSame([0, 50], [$capped?->referrerPoints, $capped?->newcomerPoints]);
        self::assertSame(1, $store->rewardedCount($this->member(1)));

        self::assertSame(100, $store->settle(ReferralId::fromString(str_repeat('c', 32)), 100, 50, 2, $at)?->referrerPoints);
        self::assertSame(2, $store->rewardedCount($this->member(1)));
        self::assertSame(0, $store->rewardedCount($this->member(2)));
    }

    public function testAnErasedReferrerIsNeverRewarded(): void
    {
        $store = new InMemoryReferralStore();
        $store->add(new Referral(ReferralId::fromString(str_repeat('a', 32)), Erasure::erasedMember(), $this->member(2), new DateTimeImmutable()));

        self::assertSame(0, $store->settle(ReferralId::fromString(str_repeat('a', 32)), 100, 50, 10, new DateTimeImmutable())?->referrerPoints);
    }

    public function testRefusingAPendingReferral(): void
    {
        $store = new InMemoryReferralStore();
        $store->add($this->referral('a', 1, 2));

        $refused = $store->refuse(ReferralId::fromString(str_repeat('a', 32)), new DateTimeImmutable());

        self::assertSame(ReferralStatus::Refused, $refused?->status);
        self::assertSame($refused, $store->ofNewcomer($this->member(2)));
        self::assertSame($refused, $store->settle(ReferralId::fromString(str_repeat('a', 32)), 1, 1, 1, new DateTimeImmutable()));
    }

    public function testErasingAMember(): void
    {
        $store = new InMemoryReferralStore();
        $store->addCode($this->member(1), ReferralCode::fromString('AAAAAAAA'));
        $store->addCode($this->member(2), ReferralCode::fromString('BBBBBBBB'));
        $store->add($this->referral('a', 2, 1));
        $store->settle(ReferralId::fromString(str_repeat('a', 32)), 1, 1, 1, new DateTimeImmutable());
        $store->add($this->referral('b', 1, 3));
        $store->add($this->referral('c', 1, 4));
        $store->refuse(ReferralId::fromString(str_repeat('c', 32)), new DateTimeImmutable());
        $store->add($this->referral('d', 2, 5));

        $erasure = $store->eraseMember($this->member(1));

        self::assertSame([1, 0, 3], [$erasure->codes, $erasure->deleted, $erasure->detached]);
        self::assertNull($store->codeOf($this->member(1)));
        self::assertNotNull($store->codeOf($this->member(2)));
        self::assertNull($store->ofNewcomer($this->member(1)));
        self::assertSame(['erased:member', 'member:5'], array_map(static fn (Referral $referral): string => $referral->newcomer->toString(), $store->ofReferrer($this->member(2), 0, 10)));
        self::assertSame('erased:member', $store->ofNewcomer($this->member(3))?->referrer->toString());
        self::assertSame('erased:member', $store->ofNewcomer($this->member(4))?->referrer->toString());
        self::assertSame(1, $store->rewardedCount($this->member(2)));

        $pending = $store->eraseMember($this->member(5));
        self::assertSame([0, 1, 0], [$pending->codes, $pending->deleted, $pending->detached]);
        self::assertSame(['erased:member'], array_map(static fn (Referral $referral): string => $referral->newcomer->toString(), $store->ofReferrer($this->member(2), 0, 10)));
        self::assertSame(['member:3', 'member:4'], array_map(static fn (Referral $referral): string => $referral->newcomer->toString(), $store->ofReferrer(Erasure::erasedMember(), 0, 10)));
    }

    public function testErasingAPendingNewcomerStillDetachesTheNextReferrals(): void
    {
        $store = new InMemoryReferralStore();
        $store->add($this->referral('a', 2, 5));
        $store->add($this->referral('b', 5, 6));

        $erasure = $store->eraseMember($this->member(5));

        self::assertSame([0, 1, 1], [$erasure->codes, $erasure->deleted, $erasure->detached]);
        self::assertSame('erased:member', $store->ofNewcomer($this->member(6))?->referrer->toString());
    }

    public function testReadingTheReferralsOfAReferrerByPage(): void
    {
        $store = new InMemoryReferralStore();
        $store->add($this->referral('a', 1, 2));
        $store->add($this->referral('b', 9, 3));
        $store->add($this->referral('c', 1, 4));
        $store->add($this->referral('d', 1, 5));

        self::assertSame(['member:4', 'member:5'], array_map(static fn (Referral $referral): string => $referral->newcomer->toString(), $store->ofReferrer($this->member(1), 1, 5)));
        self::assertSame(['member:2'], array_map(static fn (Referral $referral): string => $referral->newcomer->toString(), $store->ofReferrer($this->member(1), 0, 1)));
    }

    private function referral(string $id, int $referrer, int $newcomer): Referral
    {
        return new Referral(ReferralId::fromString(str_repeat($id, 32)), $this->member($referrer), $this->member($newcomer), new DateTimeImmutable('2026-11-20 10:00:00 UTC'));
    }

    private function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
    }
}
