<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Contract\LeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\InMemoryLeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Lead;

#[CoversClass(InMemoryLeadStore::class)]
#[CoversClass(Lead::class)]
final class InMemoryLeadStoreTest extends LeadStoreTestCase
{
    public function testTheStoreIsEmptiedBetweenTwoRequests(): void
    {
        $store = new InMemoryLeadStore();
        $store->claim(new Lead('games', 'ever', ResourceReference::fromString('member:1'), new DateTimeImmutable('2026-11-07 UTC')));
        $store->reset();

        self::assertFalse($store->has('games', 'ever', ResourceReference::fromString('member:1')));
    }

    public function testABoardAndAPeriodAreNotMistakenForAnother(): void
    {
        $store = new InMemoryLeadStore();
        $store->claim(new Lead('a', 'b-c', ResourceReference::fromString('member:1'), new DateTimeImmutable('2026-11-07 UTC')));

        self::assertFalse($store->has('a-b', 'c', ResourceReference::fromString('member:1')));
        self::assertTrue($store->claim(new Lead('a-b', 'c', ResourceReference::fromString('member:1'), new DateTimeImmutable('2026-11-07 UTC'))));
    }

    public function testTwoPairsThatJoinToTheSameTextAreTwoLeads(): void
    {
        $store = new InMemoryLeadStore();
        $store->claim(new Lead('ab', 'c', ResourceReference::fromString('member:1'), new DateTimeImmutable('2026-11-07 UTC')));

        self::assertFalse($store->has('a', 'bc', ResourceReference::fromString('member:1')));
        self::assertFalse($store->has('abc', '', ResourceReference::fromString('member:1')));
        self::assertTrue($store->claim(new Lead('a', 'bc', ResourceReference::fromString('member:1'), new DateTimeImmutable('2026-11-07 UTC'))));
    }

    protected function store(): LeadStore
    {
        return new InMemoryLeadStore();
    }
}
