<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Tests\Unit\Infrastructure\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Progression\ReachedMilestone;
use Tilttek\Progression\Store\MilestoneStore;

/**
 * The contract every milestone store passes: extend it with the store to test.
 */
abstract class MilestoneStoreTestCase extends TestCase
{
    abstract protected function store(): MilestoneStore;

    public function testAMilestoneIsReachedOnce(): void
    {
        $store = $this->store();

        self::assertTrue($store->reach(self::reached('player', 'veteran', 'member:1', '', '2026-11-02 10:00')));
        self::assertFalse($store->reach(self::reached('player', 'veteran', 'member:1', '', '2026-11-03 10:00')));
        self::assertTrue($store->reach(self::reached('player', 'veteran', 'member:1', '2026-q4', '2026-11-01 10:00')));
        self::assertTrue($store->reach(self::reached('player', 'first', 'member:1', '', '2026-11-04 10:00')));
        self::assertTrue($store->reach(self::reached('gm', 'veteran', 'member:1', '', '2026-11-05 10:00')));
        self::assertTrue($store->reach(self::reached('player', 'veteran', 'member:2', '', '2026-11-02 10:00')));

        self::assertEquals([
            self::reached('player', 'veteran', 'member:1', '2026-q4', '2026-11-01 10:00'),
            self::reached('player', 'veteran', 'member:1', '', '2026-11-02 10:00'),
            self::reached('player', 'first', 'member:1', '', '2026-11-04 10:00'),
            self::reached('gm', 'veteran', 'member:1', '', '2026-11-05 10:00'),
        ], $store->of(ResourceReference::fromString('member:1')));
    }

    public function testTheNoticeOfAMilestoneIsDueUntilItIsDelivered(): void
    {
        $store = $this->store();
        $store->reach(self::reached('player', 'veteran', 'member:1', '', '2026-11-02 10:00'));
        $store->reach(self::reached('player', 'first', 'member:1', '', '2026-11-01 10:00'));
        $store->reach(self::reached('player', 'first', 'member:2', '', '2026-11-01 10:00'));

        $milestone = static fn (ReachedMilestone $reached): string => $reached->milestone;

        self::assertSame(['first', 'veteran'], array_map($milestone, $store->undelivered(ResourceReference::fromString('member:1'))));
        $store->markDelivered(self::reached('player', 'first', 'member:1', '', '2026-11-09 10:00'));
        $store->markDelivered(self::reached('player', 'first', 'member:1', '', '2026-11-09 10:00'));
        $store->markDelivered(self::reached('player', 'veteran', 'member:1', 'S1', '2026-11-09 10:00'));
        self::assertSame(['veteran'], array_map($milestone, $store->undelivered(ResourceReference::fromString('member:1'))));
        self::assertSame(['first'], array_map($milestone, $store->undelivered(ResourceReference::fromString('member:2'))));
        self::assertCount(2, $store->of(ResourceReference::fromString('member:1')));
        $store->eraseMember(ResourceReference::fromString('member:1'));
        self::assertCount(0, $store->undelivered(ResourceReference::fromString('member:1')));
        self::assertTrue($store->reach(self::reached('player', 'veteran', 'member:1', '', '2026-11-02 10:00')));
        self::assertCount(1, $store->undelivered(ResourceReference::fromString('member:1')));
    }

    public function testTheMilestonesOfAMemberForTheirExport(): void
    {
        $store = $this->store();
        $store->reach(new ReachedMilestone('character', 'level-5', ResourceReference::fromString('character:9'), ResourceReference::fromString('member:1'), '', new DateTimeImmutable('2026-11-03 10:00 UTC'), 'k1'));
        $store->reach(self::reached('player', 'veteran', 'member:1', '', '2026-11-02 10:00'));
        $store->reach(self::reached('player', 'veteran', 'member:2', '', '2026-11-02 10:00'));

        $milestone = static fn (ReachedMilestone $reached): string => $reached->milestone;

        self::assertSame(['veteran', 'level-5'], array_map($milestone, $store->ofMember(ResourceReference::fromString('member:1'))));
        self::assertSame(['veteran'], array_map($milestone, $store->ofMember(ResourceReference::fromString('member:2'))));
    }

    public function testAMemberIsErased(): void
    {
        $store = $this->store();
        $store->reach(self::reached('player', 'veteran', 'member:1', '', '2026-11-02 10:00'));
        $store->reach(new ReachedMilestone('character', 'level-5', ResourceReference::fromString('character:9'), ResourceReference::fromString('member:1'), '', new DateTimeImmutable('2026-11-02 10:00 UTC'), 'k1'));
        $store->reach(self::reached('player', 'veteran', 'member:2', '', '2026-11-02 10:00'));

        self::assertSame(2, $store->eraseMember(ResourceReference::fromString('member:1')));
        self::assertSame([], $store->of(ResourceReference::fromString('character:9')));
        self::assertCount(1, $store->of(ResourceReference::fromString('member:2')));
    }

    protected static function reached(string $track, string $milestone, string $member, string $season, string $at): ReachedMilestone
    {
        return new ReachedMilestone($track, $milestone, ResourceReference::fromString($member), ResourceReference::fromString($member), $season, new DateTimeImmutable($at.' UTC'), 'activity:'.$at);
    }
}
