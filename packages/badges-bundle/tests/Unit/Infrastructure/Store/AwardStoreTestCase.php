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
        $store->add(self::award('gold', '1', 1, '2026-11-01 10:00:00', 'a'));
        $store->add(self::award('gold', '1', 2, '2026-11-03 10:00:00', 'b'));
        $store->add(self::award('hand', '1', 1, '2026-11-04 10:00:00', null, true));
        $store->add(self::award('gold', '2', 1, '2026-11-05 10:00:00', 'c'));

        $forgotten = $store->forgetDerived(new DateTimeImmutable('2026-11-03 10:00:00 UTC'));
        usort($forgotten, static fn (Award $x, Award $y): int => $x->member->toString() <=> $y->member->toString());
        self::assertSame([['gold', 'member:1', 2, '2026-11-03T10:00:00+00:00', 'b', false], ['gold', 'member:2', 1, '2026-11-05T10:00:00+00:00', 'c', false]], array_map($this->plain(...), $forgotten));
        self::assertSame(['gold', 'hand'], array_map(static fn (Award $award): string => $award->badge, $store->of(ResourceReference::of('member', '1'))));
        self::assertSame([['gold', 'member:1', 1, '2026-11-01T10:00:00+00:00', 'a', false]], array_map($this->plain(...), $store->forgetDerived(null)));
        self::assertSame(['hand'], array_map(static fn (Award $award): string => $award->badge, $store->of(ResourceReference::of('member', '1'))));
        self::assertSame([], $store->forgetDerived(null));
    }

    public function testAMemberIsErased(): void
    {
        $store = $this->store();
        $store->add(self::award('gold', '1', 1, '2026-11-01 10:00:00'));
        $store->add(self::award('hand', '1', 1, '2026-11-01 10:00:00', null, true));
        $store->add(self::award('gold', '2', 1, '2026-11-01 10:00:00'));

        self::assertSame(2, $store->eraseMember(ResourceReference::of('member', '1')));
        self::assertSame([], $store->of(ResourceReference::of('member', '1')));
        self::assertCount(1, $store->of(ResourceReference::of('member', '2')));
        self::assertSame(0, $store->eraseMember(ResourceReference::of('member', '1')));
    }

    abstract protected function store(): AwardStore;

    protected static function award(string $badge, string $member, int $number, string $at, ?string $cause = null, bool $manual = false): Award
    {
        return new Award($badge, ResourceReference::of('member', $member), $number, new DateTimeImmutable($at.' UTC'), $cause, $manual);
    }

    /**
     * @return array{string, string, int, string, ?string, bool}
     */
    private function plain(Award $award): array
    {
        return [$award->badge, $award->member->toString(), $award->number, $award->awardedAt->format(DateTimeInterface::ATOM), $award->cause, $award->manual];
    }
}
