<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Unit\Infrastructure\Store;

use DateTimeImmutable;
use DateTimeInterface;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Badges\Contribution;
use Tilttek\Badges\Store\ProgressStore;

/**
 * The contract of a `ProgressStore`, which every implementation passes.
 */
abstract class ProgressStoreTestCase extends TestCase
{
    public function testAContributionIsKeptOnce(): void
    {
        $store = $this->store();
        $member = ResourceReference::of('member', '1');

        self::assertTrue($store->add(self::contribution('gold', 'r', '1', 'a', 2, '2026-11-01 10:00:00')));
        self::assertFalse($store->add(self::contribution('gold', 'r', '1', 'a', 5, '2026-11-02 10:00:00')));
        self::assertTrue($store->add(self::contribution('gold', 'r.0', '1', 'a', 2, '2026-11-01 10:00:00')));
        self::assertTrue($store->add(self::contribution('silver', 'r', '1', 'a', 2, '2026-11-01 10:00:00')));
        self::assertTrue($store->add(self::contribution('gold', 'r', '1', 'b', 3, '2026-11-03 10:00:00')));
        self::assertTrue($store->add(self::contribution('gold', 'r', '2', 'c', 7, '2026-11-03 10:00:00')));

        self::assertSame(2, $store->count('gold', 'r', $member));
        self::assertSame(5, $store->quantity('gold', 'r', $member));
        self::assertSame(1, $store->count('gold', 'r.0', $member));
        self::assertSame(1, $store->count('gold', 'r', ResourceReference::of('member', '2')));
        self::assertSame(0, $store->count('gold', 'r', ResourceReference::of('member', '3')));
        self::assertSame(0, $store->quantity('bronze', 'r', $member));
        self::assertSame(['2026-11-01T10:00:00+00:00', '2026-11-03T10:00:00+00:00'], $this->moments($store->moments('gold', 'r', $member)));
        self::assertSame([], $store->moments('gold', 'r.1', $member));
        self::assertSame([0, 1], array_keys($store->moments('gold', 'r', $member)));
    }

    public function testACancellationRemovesTheActivitysContributions(): void
    {
        $store = $this->store();
        $store->add(self::contribution('gold', 'r', '1', 'a', 2, '2026-11-01 10:00:00'));
        $store->add(self::contribution('silver', 'r.1', '1', 'a', 2, '2026-11-01 10:00:00'));
        $store->add(self::contribution('gold', 'r', '1', 'b', 3, '2026-11-03 10:00:00'));

        $removed = $store->remove('a');
        self::assertSame([0, 1], array_keys($removed));
        usort($removed, static fn (Contribution $x, Contribution $y): int => $x->badge <=> $y->badge);
        self::assertSame([['gold', 'r', 'member:1', 'a', 2, '2026-11-01T10:00:00+00:00'], ['silver', 'r.1', 'member:1', 'a', 2, '2026-11-01T10:00:00+00:00']], array_map($this->plain(...), $removed));
        self::assertSame([], $store->remove('a'));
        self::assertSame(1, $store->count('gold', 'r', ResourceReference::of('member', '1')));
        self::assertTrue($store->add(self::contribution('gold', 'r', '1', 'a', 2, '2026-11-01 10:00:00')));
    }

    public function testARebuildForgetsAPeriod(): void
    {
        $store = $this->store();
        $member = ResourceReference::of('member', '1');
        $store->add(self::contribution('gold', 'r', '1', 'a', 1, '2026-11-01 10:00:00'));
        $store->add(self::contribution('gold', 'r', '1', 'b', 1, '2026-11-03 10:00:00'));
        $store->add(self::contribution('gold', 'r', '1', 'c', 1, '2026-11-04 10:00:00'));

        self::assertSame(2, $store->forget(new DateTimeImmutable('2026-11-03 10:00:00 UTC')));
        self::assertSame(1, $store->count('gold', 'r', $member));
        self::assertSame(0, $store->forget(new DateTimeImmutable('2026-11-03 10:00:00 UTC')));
        self::assertSame(1, $store->forget(null));
        self::assertSame(0, $store->count('gold', 'r', $member));
    }

    public function testAMemberIsErased(): void
    {
        $store = $this->store();
        $store->add(self::contribution('gold', 'r', '1', 'a', 1, '2026-11-01 10:00:00'));
        $store->add(self::contribution('silver', 'r', '1', 'b', 1, '2026-11-03 10:00:00'));
        $store->add(self::contribution('gold', 'r', '2', 'c', 1, '2026-11-04 10:00:00'));

        self::assertSame(2, $store->eraseMember(ResourceReference::of('member', '1')));
        self::assertSame(0, $store->count('gold', 'r', ResourceReference::of('member', '1')));
        self::assertSame(1, $store->count('gold', 'r', ResourceReference::of('member', '2')));
        self::assertSame(0, $store->eraseMember(ResourceReference::of('member', '1')));
    }

    abstract protected function store(): ProgressStore;

    protected static function contribution(string $badge, string $path, string $member, string $activity, int $quantity, string $at): Contribution
    {
        return new Contribution($badge, $path, ResourceReference::of('member', $member), $activity, $quantity, new DateTimeImmutable($at.' UTC'));
    }

    /**
     * @param list<DateTimeImmutable> $moments
     *
     * @return list<string>
     */
    private function moments(array $moments): array
    {
        $formatted = array_map(static fn (DateTimeImmutable $moment): string => $moment->format(DateTimeInterface::ATOM), $moments);
        sort($formatted);

        return $formatted;
    }

    /**
     * @return array{string, string, string, string, int, string}
     */
    private function plain(Contribution $contribution): array
    {
        return [$contribution->badge, $contribution->path, $contribution->member->toString(), $contribution->activity, $contribution->quantity, $contribution->occurredAt->format(DateTimeInterface::ATOM)];
    }
}
