<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\AdventCalendar\Contract\OpeningStore;
use Tilttek\AdventCalendar\Opening;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberOpenings;

/**
 * The contract every store of the bundle passes: one opening per calendar, member and day, the openings of a member by
 * calendar then day, and their erasure.
 */
abstract class OpeningStoreTestCase extends TestCase
{
    abstract protected function store(): OpeningStore&MemberOpenings;

    public function testAnOpeningIsKeptOnceAndReadBack(): void
    {
        $store = $this->store();
        $ann = ResourceReference::of('member', '44');

        self::assertNull($store->find('advent', ResourceReference::of('member', '44'), 3));
        self::assertTrue($store->add(self::opening('advent', $ann, 3, '2026-12-03 10:00:00')));
        self::assertFalse($store->add(self::opening('advent', $ann, 3, '2026-12-04 11:00:00')));

        $opening = $store->find('advent', $ann, 3);
        self::assertInstanceOf(Opening::class, $opening);
        self::assertSame(['calendar' => 'advent', 'member' => 'member:44', 'day' => 3, 'opened_at' => '2026-12-03T15:00:00+00:00'], $opening->toArray());
        self::assertSame(0, $opening->openedAt->getTimezone()->getOffset($opening->openedAt));
        self::assertNull($store->find('advent', $ann, 4));
        self::assertNull($store->find('other', $ann, 3));
        self::assertNull($store->find('advent', ResourceReference::of('member', '45'), 3));
    }

    public function testAnOpeningIsKeptUndeliveredUntilItIsMarkedDelivered(): void
    {
        $store = $this->store();
        $ann = ResourceReference::of('member', '44');
        $store->add(self::opening('advent', $ann, 3, '2026-12-03 10:00:00'));
        $store->add(self::opening('advent', $ann, 1, '2026-12-03 11:00:00'));
        $store->add(self::opening('advent-2025', $ann, 5, '2026-12-03 12:00:00'));
        $store->add(self::opening('advent', ResourceReference::of('member', '45'), 1, '2026-12-03 10:00:00'));

        $key = static fn (Opening $opening): string => $opening->calendar.':'.$opening->day;

        self::assertSame(['advent:1', 'advent:3', 'advent-2025:5'], array_map($key, $store->undelivered($ann)));
        self::assertSame('2026-12-03T15:00:00+00:00', $store->undelivered($ann)[1]->toArray()['opened_at']);
        $store->markDelivered(self::opening('advent', $ann, 3, '2026-12-09 10:00:00'));
        $store->markDelivered(self::opening('advent', $ann, 3, '2026-12-09 10:00:00'));
        $store->markDelivered(self::opening('advent', $ann, 9, '2026-12-09 10:00:00'));
        self::assertSame(['advent:1', 'advent-2025:5'], array_map($key, $store->undelivered($ann)));
        self::assertSame(['advent:1'], array_map($key, $store->undelivered(ResourceReference::of('member', '45'))));
        self::assertNotNull($store->find('advent', $ann, 3));
        self::assertSame(3, $store->eraseMember($ann));
        self::assertCount(0, $store->undelivered($ann));
    }

    public function testTheOpeningsOfAMemberAreReadByCalendarThenDay(): void
    {
        $store = $this->store();
        $ann = ResourceReference::of('member', '44');
        $bob = ResourceReference::of('member', '45');
        $store->add(self::opening('winter', $ann, 2, '2026-12-02 10:00:00'));
        $store->add(self::opening('advent', $ann, 4, '2026-12-04 10:00:00'));
        $store->add(self::opening('advent', $ann, 1, '2026-12-05 10:00:00'));
        $store->add(self::opening('advent', $bob, 2, '2026-12-02 10:00:00'));

        self::assertSame([1, 4], array_map(static fn (Opening $opening): int => $opening->day, $store->openingsOf('advent', $ann)));
        self::assertSame([], $store->openingsOf('summer', $ann));
        self::assertSame(['advent:1', 'advent:4', 'winter:2'], array_map(static fn (Opening $opening): string => $opening->calendar.':'.$opening->day, $store->ofMember($ann)));
        self::assertSame([], $store->ofMember(ResourceReference::of('member', '46')));

        self::assertSame(3, $store->eraseMember($ann));
        self::assertSame([], $store->ofMember($ann));
        self::assertSame([], $store->openingsOf('advent', $ann));
        self::assertCount(1, $store->openingsOf('advent', $bob));
        self::assertSame(0, $store->eraseMember($ann));
    }

    protected static function opening(string $calendar, ResourceReference $member, int $day, string $at): Opening
    {
        return new Opening($calendar, $member, $day, new DateTimeImmutable($at, new DateTimeZone('America/Toronto')));
    }
}
