<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Unit\Infrastructure\Store;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Badges\Award;
use Tilttek\Badges\Store\AwardStore;

/**
 * The contract of an `AwardStore`, which every implementation passes.
 */
abstract class AwardStoreTestCase extends TestCase
{
    public function testAnAwardIsKeptOncePerNumber(): void
    {
        $store = $this->store();
        $member = ResourceReference::of('member', '1');

        self::assertTrue($store->add(self::award('silver', '1', 1, '2026-11-03 10:00:00', 'b')));
        self::assertTrue($store->add(self::award('gold', '1', 1, '2026-11-01 10:00:00', 'a')));
        self::assertFalse($store->add(self::award('gold', '1', 1, '2026-11-05 10:00:00', 'c')));
        self::assertTrue($store->add(self::award('gold', '1', 2, '2026-11-03 10:00:00', null, true)));
        self::assertTrue($store->add(self::award('gold', '2', 1, '2026-11-01 10:00:00')));

        self::assertSame([
            ['gold', 'member:1', 1, '2026-11-01T10:00:00+00:00', 'a', false],
            ['gold', 'member:1', 2, '2026-11-03T10:00:00+00:00', null, true],
            ['silver', 'member:1', 1, '2026-11-03T10:00:00+00:00', 'b', false],
        ], array_map($this->plain(...), $store->of($member)));
        self::assertSame([['silver', 'member:1', 1, '2026-11-03T10:00:00+00:00', 'b', false]], array_map($this->plain(...), $store->of($member, 'silver')));
        self::assertSame([], $store->of($member, 'bronze'));
        self::assertSame([], $store->of(ResourceReference::of('member', '3')));
    }

    public function testAnAwardIsRemoved(): void
    {
        $store = $this->store();
        $member = ResourceReference::of('member', '1');
        $store->add(self::award('gold', '1', 1, '2026-11-01 10:00:00'));
        $store->add(self::award('gold', '1', 2, '2026-11-02 10:00:00'));
        $store->add(self::award('gold', '2', 2, '2026-11-02 10:00:00'));

        $store->remove(self::award('gold', '1', 2, '2026-11-09 10:00:00'));
        self::assertSame([1], array_map(static fn (Award $award): int => $award->number, $store->of($member)));
        self::assertCount(1, $store->of(ResourceReference::of('member', '2')));
        $store->remove(self::award('gold', '1', 2, '2026-11-09 10:00:00'));
        self::assertCount(1, $store->of($member));
    }

    public function testARebuildForgetsTheDerivedAwardsOfAPeriod(): void
    {
        $store = $this->store();

        foreach ([self::award('gold', '1', 1, '2026-11-01 10:00:00', 'a'), self::award('gold', '1', 2, '2026-11-03 10:00:00', 'b'), self::award('gold', '2', 1, '2026-11-05 10:00:00', 'c')] as $award) {
            $store->add($award);
            $store->markDelivered($award);
        }

        $store->add(self::award('hand', '1', 1, '2026-11-04 10:00:00', null, true));
        // An award whose notice is due is forgotten without being returned; so is a withdrawn award.
        $store->add(self::award('gold', '3', 1, '2026-11-05 10:00:00', 'd'));

        $withdrawn = self::award('gold', '3', 2, '2026-11-05 10:00:00', 'e');
        $store->add($withdrawn);
        $store->markDelivered($withdrawn);
        $store->withdraw($withdrawn);

        $kept = self::award('gold', '3', 3, '2026-11-01 10:00:00', 'f');
        $store->add($kept);
        $store->markDelivered($kept);
        $store->withdraw($kept);

        $forgotten = $store->forgetDerived(new DateTimeImmutable('2026-11-03 10:00:00 UTC'));
        usort($forgotten, static fn (Award $x, Award $y): int => $x->member->toString() <=> $y->member->toString());
        self::assertSame([['gold', 'member:1', 2, '2026-11-03T10:00:00+00:00', 'b', false], ['gold', 'member:2', 1, '2026-11-05T10:00:00+00:00', 'c', false]], array_map($this->plain(...), $forgotten));
        self::assertSame(['gold', 'hand'], array_map(static fn (Award $award): string => $award->badge, $store->of(ResourceReference::of('member', '1'))));
        self::assertSame([['gold', 3]], $this->numbers($store->withdrawn(ResourceReference::of('member', '3'))));
        self::assertSame([], $store->undelivered(ResourceReference::of('member', '3')));
        self::assertSame([['gold', 'member:1', 1, '2026-11-01T10:00:00+00:00', 'a', false]], array_map($this->plain(...), $store->forgetDerived(null)));
        self::assertSame(['hand'], array_map(static fn (Award $award): string => $award->badge, $store->of(ResourceReference::of('member', '1'))));
        self::assertSame([], $store->forgetDerived(null));
        self::assertSame([], $store->of(ResourceReference::of('member', '3')));
        self::assertSame([], $store->undelivered(ResourceReference::of('member', '3')));
        self::assertSame([], $store->withdrawn(ResourceReference::of('member', '3')));
    }

    public function testTheNoticeOfAnAwardIsDueUntilItIsDelivered(): void
    {
        $store = $this->store();
        $member = ResourceReference::of('member', '1');
        $store->add(self::award('silver', '1', 1, '2026-11-01 10:00:00', 'a'));
        $store->add(self::award('gold', '1', 2, '2026-11-02 10:00:00', 'b'));
        $store->add(self::award('gold', '1', 1, '2026-11-03 10:00:00', 'c'));
        $store->add(self::award('hand', '1', 1, '2026-11-03 10:00:00', null, true));
        $store->add(self::award('gold', '2', 1, '2026-11-03 10:00:00', 'd'));

        self::assertSame([['gold', 1], ['gold', 2], ['silver', 1]], $this->numbers($store->undelivered($member)));
        $store->markDelivered(self::award('gold', '1', 1, '2026-11-09 10:00:00'));
        $store->markDelivered(self::award('gold', '1', 1, '2026-11-09 10:00:00'));
        self::assertSame([['gold', 2], ['silver', 1]], $this->numbers($store->undelivered($member)));
        self::assertSame([['gold', 1]], $this->numbers($store->undelivered(ResourceReference::of('member', '2'))));
        self::assertCount(4, $store->of($member));
    }

    public function testAWithdrawnAwardWaitsForItsNotice(): void
    {
        $store = $this->store();
        $member = ResourceReference::of('member', '1');

        foreach ([self::award('gold', '1', 2, '2026-11-01 10:00:00', 'a'), self::award('gold', '1', 1, '2026-11-01 10:00:00', 'b'), self::award('gold', '2', 1, '2026-11-01 10:00:00', 'c')] as $award) {
            $store->add($award);
            $store->markDelivered($award);
        }

        $store->add(self::award('silver', '1', 1, '2026-11-01 10:00:00', 'd'));

        self::assertTrue($store->withdraw(self::award('gold', '1', 2, '2026-11-09 10:00:00')));
        self::assertTrue($store->withdraw(self::award('gold', '1', 1, '2026-11-09 10:00:00')));
        self::assertFalse($store->withdraw(self::award('gold', '1', 1, '2026-11-09 10:00:00')));
        self::assertTrue($store->withdraw(self::award('gold', '2', 1, '2026-11-09 10:00:00')));
        // Withdrawn before its own notice was given, an award is owed the notice of its withdrawal all the same.
        self::assertTrue($store->withdraw(self::award('silver', '1', 1, '2026-11-09 10:00:00')));
        self::assertFalse($store->withdraw(self::award('bronze', '1', 1, '2026-11-09 10:00:00')));

        self::assertSame([], $store->of($member));
        self::assertSame([], $store->undelivered($member));
        self::assertSame([['gold', 'member:1', 1, '2026-11-01T10:00:00+00:00', 'b', false], ['gold', 'member:1', 2, '2026-11-01T10:00:00+00:00', 'a', false], ['silver', 'member:1', 1, '2026-11-01T10:00:00+00:00', 'd', false]], array_map($this->plain(...), $store->withdrawn($member)));
        self::assertFalse($store->add(self::award('gold', '1', 1, '2026-11-09 10:00:00')));
        self::assertFalse($store->add(self::award('silver', '1', 1, '2026-11-09 10:00:00')));

        $store->remove(self::award('gold', '1', 1, '2026-11-09 10:00:00'));
        $store->remove(self::award('silver', '1', 1, '2026-11-09 10:00:00'));
        self::assertSame([['gold', 2]], $this->numbers($store->withdrawn($member)));
        self::assertSame([['gold', 1]], $this->numbers($store->withdrawn(ResourceReference::of('member', '2'))));
        self::assertTrue($store->add(self::award('gold', '1', 1, '2026-11-09 10:00:00')));
        self::assertTrue($store->add(self::award('silver', '1', 1, '2026-11-09 10:00:00')));
        self::assertSame([['gold', 1], ['silver', 1]], $this->numbers($store->undelivered($member)));
    }

    public function testAnAwardWithdrawnWhileItsNoticeIsUnderWayIsOwedTheNoticeOfItsWithdrawal(): void
    {
        $store = $this->store();
        $member = ResourceReference::of('member', '1');
        $store->add(self::award('gold', '1', 1, '2026-11-01 10:00:00', 'a'));
        $store->add(self::award('gold', '1', 2, '2026-11-01 10:00:00', 'b'));

        // A process announces the first award; another withdraws it before the first notes its notice given.
        self::assertTrue($store->withdraw(self::award('gold', '1', 1, '2026-11-09 10:00:00')));
        $store->markDelivered(self::award('gold', '1', 1, '2026-11-09 10:00:00'));
        // The second is announced and noted, then withdrawn.
        $store->markDelivered(self::award('gold', '1', 2, '2026-11-09 10:00:00'));
        self::assertTrue($store->withdraw(self::award('gold', '1', 2, '2026-11-09 10:00:00')));
        $store->markDelivered(self::award('gold', '1', 2, '2026-11-09 10:00:00'));

        self::assertSame([], $store->of($member));
        self::assertSame([], $store->undelivered($member));
        self::assertSame([['gold', 1], ['gold', 2]], $this->numbers($store->withdrawn($member)));
        self::assertFalse($store->withdraw(self::award('gold', '1', 1, '2026-11-09 10:00:00')));
    }

    public function testAMemberIsErased(): void
    {
        $store = $this->store();
        $store->add(self::award('gold', '1', 1, '2026-11-01 10:00:00'));
        $store->add(self::award('hand', '1', 1, '2026-11-01 10:00:00', null, true));
        $store->add(self::award('gold', '1', 2, '2026-11-01 10:00:00'));
        $store->markDelivered(self::award('gold', '1', 2, '2026-11-01 10:00:00'));
        $store->withdraw(self::award('gold', '1', 2, '2026-11-01 10:00:00'));
        $store->add(self::award('gold', '2', 1, '2026-11-01 10:00:00'));
        $store->add(self::award('gold', '2', 2, '2026-11-01 10:00:00'));
        $store->markDelivered(self::award('gold', '2', 2, '2026-11-01 10:00:00'));
        $store->withdraw(self::award('gold', '2', 2, '2026-11-01 10:00:00'));
        $store->add(self::award('gold', '2', 3, '2026-11-01 10:00:00'));
        $store->markDelivered(self::award('gold', '2', 3, '2026-11-01 10:00:00'));

        self::assertSame(3, $store->eraseMember(ResourceReference::of('member', '1')));
        self::assertSame([], $store->of(ResourceReference::of('member', '1')));
        self::assertSame([], $store->undelivered(ResourceReference::of('member', '1')));
        self::assertSame([], $store->withdrawn(ResourceReference::of('member', '1')));
        self::assertCount(2, $store->of(ResourceReference::of('member', '2')));
        self::assertSame([['gold', 1]], $this->numbers($store->undelivered(ResourceReference::of('member', '2'))));
        self::assertSame([['gold', 2]], $this->numbers($store->withdrawn(ResourceReference::of('member', '2'))));
        self::assertSame(0, $store->eraseMember(ResourceReference::of('member', '1')));
    }

    abstract protected function store(): AwardStore;

    protected static function award(string $badge, string $member, int $number, string $at, ?string $cause = null, bool $manual = false): Award
    {
        return new Award($badge, ResourceReference::of('member', $member), $number, new DateTimeImmutable($at.' UTC'), $cause, $manual);
    }

    /**
     * @param list<Award> $awards
     *
     * @return list<array{string, int}>
     */
    private function numbers(array $awards): array
    {
        return array_map(static fn (Award $award): array => [$award->badge, $award->number], $awards);
    }

    /**
     * @return array{string, string, int, string, ?string, bool}
     */
    private function plain(Award $award): array
    {
        return [$award->badge, $award->member->toString(), $award->number, $award->awardedAt->format(DateTimeInterface::ATOM), $award->cause, $award->manual];
    }
}
