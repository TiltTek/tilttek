<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendar\Tests\Unit\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\AdventCalendar\Contract\OpeningStore;
use Tilttek\AdventCalendar\Opening;

/**
 * The contract any opening store passes: extend it with the store to test.
 */
abstract class OpeningStoreTestCase extends TestCase
{
    abstract protected function store(): OpeningStore;

    public function testAnOpeningIsKeptOnce(): void
    {
        $store = $this->store();
        $member = ResourceReference::of('member', '1');
        $first = new Opening('advent-2026', $member, 3, new DateTimeImmutable('2026-12-03 08:00 UTC'));

        self::assertNull($store->find('advent-2026', $member, 3));
        self::assertTrue($store->add($first));
        self::assertFalse($store->add(new Opening('advent-2026', $member, 3, new DateTimeImmutable('2026-12-04 08:00 UTC'))));
        self::assertEquals($first, $store->find('advent-2026', $member, 3));
        self::assertNull($store->find('advent-2026', $member, 4));
        self::assertNull($store->find('advent-2027', $member, 3));
        self::assertNull($store->find('advent-2026', ResourceReference::of('member', '2'), 3));
    }

    public function testTheOpeningsOfAMemberComeByDay(): void
    {
        $store = $this->store();
        $member = ResourceReference::of('member', '1');

        foreach ([5, 1, 3] as $day) {
            $store->add(new Opening('advent-2026', $member, $day, new DateTimeImmutable('2026-12-10 08:00 UTC')));
        }

        $store->add(new Opening('advent-2026', ResourceReference::of('member', '2'), 2, new DateTimeImmutable('2026-12-10 08:00 UTC')));
        $store->add(new Opening('other', $member, 2, new DateTimeImmutable('2026-12-10 08:00 UTC')));

        self::assertSame([1, 3, 5], array_map(static fn (Opening $opening): int => $opening->day, $store->openingsOf('advent-2026', $member)));
        self::assertSame([], $store->openingsOf('advent-2026', ResourceReference::of('member', '3')));
    }

    public function testErasingAMemberRemovesTheirOpeningsInEveryCalendar(): void
    {
        $store = $this->store();
        $member = ResourceReference::of('member', '1');
        $other = ResourceReference::of('member', '2');
        $store->add(new Opening('advent-2026', $member, 1, new DateTimeImmutable('2026-12-01 08:00 UTC')));
        $store->add(new Opening('advent-2026', $member, 2, new DateTimeImmutable('2026-12-02 08:00 UTC')));
        $store->add(new Opening('other', $member, 1, new DateTimeImmutable('2026-12-01 08:00 UTC')));
        $store->add(new Opening('advent-2026', $other, 1, new DateTimeImmutable('2026-12-01 08:00 UTC')));

        self::assertSame(3, $store->eraseMember($member));
        self::assertSame([], $store->openingsOf('advent-2026', $member));
        self::assertSame([], $store->openingsOf('other', $member));
        self::assertCount(1, $store->openingsOf('advent-2026', $other));
        self::assertSame(0, $store->eraseMember($member));
        self::assertTrue($store->add(new Opening('advent-2026', $member, 1, new DateTimeImmutable('2026-12-05 08:00 UTC'))));
    }
}
