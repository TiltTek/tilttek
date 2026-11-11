<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit\Store;

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
