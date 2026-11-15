<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Store;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Contract\LeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Lead;

/**
 * The contract every store of leads passes: extend it with the store to test.
 */
abstract class LeadStoreTestCase extends TestCase
{
    abstract protected function store(): LeadStore;

    public function testALeadIsClaimedOncePerBoardPeriodAndMember(): void
    {
        $store = $this->store();
        $one = ResourceReference::fromString('member:1');
        $two = ResourceReference::fromString('member:2');

        self::assertFalse($store->has('games', '2026-11', $one));
        self::assertTrue($store->claim($this->lead('games', '2026-11', 'member:1', '2026-11-03 10:00')));
        self::assertFalse($store->claim($this->lead('games', '2026-11', 'member:1', '2026-11-04 10:00')));
        self::assertTrue($store->claim($this->lead('games', '2026-11', 'member:2', '2026-11-05 10:00')));
        self::assertTrue($store->claim($this->lead('games', '2026-10', 'member:1', '2026-10-05 10:00')));
        self::assertTrue($store->claim($this->lead('games@0123456789abcdef01234567', '2026-11', 'member:1', '2026-11-06 10:00')));

        self::assertTrue($store->has('games', '2026-11', $one));
        self::assertTrue($store->has('games', '2026-11', $two));
        self::assertFalse($store->has('games', '2026-10', $two));
        self::assertFalse($store->has('other', '2026-11', $one));
        self::assertEquals([
            $this->lead('games', '2026-10', 'member:1', '2026-10-05 10:00'),
            $this->lead('games', '2026-11', 'member:1', '2026-11-03 10:00'),
            $this->lead('games@0123456789abcdef01234567', '2026-11', 'member:1', '2026-11-06 10:00'),
        ], $store->leads($one));
        self::assertSame([], $store->leads(ResourceReference::fromString('member:9')));
    }

    public function testAMomentIsReadBackInUtc(): void
    {
        $store = $this->store();
        $store->claim(new Lead('games', 'ever', ResourceReference::fromString('member:1'), new DateTimeImmutable('2026-11-07 10:00:00 America/Toronto')));

        self::assertSame('2026-11-07T15:00:00+00:00', $store->leads(ResourceReference::fromString('member:1'))[0]->takenAt->setTimezone(new DateTimeZone('UTC'))->format(\DATE_ATOM));
    }

    public function testAMemberIsErased(): void
    {
        $store = $this->store();
        $store->claim($this->lead('games', '2026-11', 'member:1', '2026-11-03 10:00'));
        $store->claim($this->lead('games', '2026-10', 'member:1', '2026-10-03 10:00'));
        $store->claim($this->lead('games', '2026-11', 'member:2', '2026-11-03 11:00'));

        self::assertSame(2, $store->eraseMember(ResourceReference::fromString('member:1')));
        self::assertSame(0, $store->eraseMember(ResourceReference::fromString('member:1')));
        self::assertSame([], $store->leads(ResourceReference::fromString('member:1')));
        self::assertTrue($store->has('games', '2026-11', ResourceReference::fromString('member:2')));
        self::assertTrue($store->claim($this->lead('games', '2026-11', 'member:1', '2026-11-08 10:00')), 'A member erased can take the lead again.');
    }

    private function lead(string $board, string $period, string $member, string $at): Lead
    {
        return new Lead($board, $period, ResourceReference::fromString($member), new DateTimeImmutable($at.' UTC'));
    }
}
