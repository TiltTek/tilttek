<?php

declare(strict_types=1);

namespace Tilttek\Progression\Tests\Unit\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Progression\Store\StreakStore;
use Tilttek\Progression\Streak\StreakGesture;
use Tilttek\Progression\Streak\StreakState;

/**
 * The contract every streak store passes: extend it with the store to test.
 */
abstract class StreakStoreTestCase extends TestCase
{
    abstract protected function store(): StreakStore;

    public function testAGestureIsKeptOnce(): void
    {
        $store = $this->store();

        self::assertTrue($store->add(self::gesture('daily', 'member:1', 'k1', 12, '2026-11-01 10:00')));
        self::assertFalse($store->add(self::gesture('daily', 'member:1', 'k1', 13, '2026-11-01 10:00')));
        self::assertTrue($store->add(self::gesture('weekly', 'member:1', 'k1', 2, '2026-11-01 10:00')));
        self::assertTrue($store->add(self::gesture('daily', 'member:1', 'k2', 10, '2026-11-01 11:00')));
        self::assertTrue($store->add(self::gesture('daily', 'member:1', 'k3', 12, '2026-11-01 12:00')));
        self::assertTrue($store->add(self::gesture('daily', 'member:2', 'k4', 11, '2026-11-01 12:00')));

        self::assertSame([10, 12], $store->units('daily', self::ref('member:1')));
        self::assertSame([2], $store->units('weekly', self::ref('member:1')));
        self::assertSame([], $store->units('daily', self::ref('member:3')));
    }

    public function testACancellationRemovesTheGesturesOfItsActivity(): void
    {
        $store = $this->store();
        $store->add(self::gesture('daily', 'member:1', 'k1', 12, '2026-11-01 10:00'));
        $store->add(self::gesture('weekly', 'member:1', 'k1', 2, '2026-11-01 10:00'));
        $store->add(self::gesture('daily', 'member:1', 'k2', 13, '2026-11-02 10:00'));

        $removed = $store->remove('k1');
        self::assertSame([0, 1], array_keys($removed));
        usort($removed, static fn (StreakGesture $a, StreakGesture $b): int => $a->streak <=> $b->streak);

        self::assertEquals([self::gesture('daily', 'member:1', 'k1', 12, '2026-11-01 10:00'), self::gesture('weekly', 'member:1', 'k1', 2, '2026-11-01 10:00')], $removed);
        self::assertSame([], $store->remove('k1'));
        self::assertSame([13], $store->units('daily', self::ref('member:1')));
    }

    public function testFrozenUnits(): void
    {
        $store = $this->store();

        self::assertTrue($store->freeze('daily', self::ref('member:1'), 14));
        self::assertFalse($store->freeze('daily', self::ref('member:1'), 14));
        self::assertTrue($store->freeze('daily', self::ref('member:1'), 12));
        self::assertTrue($store->freeze('weekly', self::ref('member:1'), 1));
        self::assertTrue($store->freeze('daily', self::ref('member:2'), 13));
        self::assertSame([12, 14], $store->frozen('daily', self::ref('member:1')));
        self::assertTrue($store->thaw('daily', self::ref('member:1'), 14));
        self::assertFalse($store->thaw('daily', self::ref('member:1'), 14));
        self::assertSame([12], $store->frozen('daily', self::ref('member:1')));
        self::assertSame([1], $store->frozen('weekly', self::ref('member:1')));
    }

    public function testStates(): void
    {
        $store = $this->store();
        $alive = new StreakState('daily', self::ref('member:1'), 3, 5, 10, 12, 'America/Toronto');
        $broken = new StreakState('daily', self::ref('member:2'), 0, 4, null, 8, 'UTC');
        $recent = new StreakState('daily', self::ref('member:3'), 1, 1, 20, 20, 'UTC');
        $other = new StreakState('weekly', self::ref('member:1'), 2, 2, 1, 2, 'UTC');

        self::assertNull($store->state('daily', self::ref('member:1')));

        foreach ([$alive, $broken, $recent, $other] as $state) {
            $store->save($state);
        }

        self::assertEquals($alive, $store->state('daily', self::ref('member:1')));
        self::assertEquals($broken, $store->state('daily', self::ref('member:2')));
        self::assertEquals([$alive], $store->alive('daily', 13));
        self::assertSame([], $store->alive('daily', 12));
        self::assertEquals([$alive, $recent], $this->sorted($store->alive('daily', 21)));

        $moved = new StreakState('daily', self::ref('member:1'), 0, 5, null, 12, 'Europe/Paris');
        $store->save($moved);
        self::assertEquals($moved, $store->state('daily', self::ref('member:1')));
        $store->discard('daily', self::ref('member:1'));
        self::assertNull($store->state('daily', self::ref('member:1')));
        self::assertEquals($other, $store->state('weekly', self::ref('member:1')));
    }

    public function testARebuildForgetsTheGesturesOfAPeriod(): void
    {
        $store = $this->store();
        $store->add(self::gesture('daily', 'member:1', 'k1', 10, '2026-10-01 10:00'));
        $store->add(self::gesture('daily', 'member:1', 'k2', 11, '2026-11-01 10:00'));
        $store->add(self::gesture('daily', 'member:1', 'k3', 12, '2026-11-02 10:00'));
        $store->freeze('daily', self::ref('member:1'), 13);

        self::assertSame(2, $store->forget(new DateTimeImmutable('2026-11-01 10:00 UTC')));
        self::assertSame([10], $store->units('daily', self::ref('member:1')));
        self::assertSame(1, $store->forget(null));
        self::assertSame([], $store->units('daily', self::ref('member:1')));
        self::assertSame([13], $store->frozen('daily', self::ref('member:1')));
    }

    public function testAMemberIsErased(): void
    {
        $store = $this->store();
        $store->add(self::gesture('daily', 'member:1', 'k1', 10, '2026-10-01 10:00'));
        $store->add(self::gesture('weekly', 'member:1', 'k1', 1, '2026-10-01 10:00'));
        $store->add(self::gesture('daily', 'member:2', 'k2', 10, '2026-10-01 10:00'));
        $store->freeze('daily', self::ref('member:1'), 11);
        $store->freeze('daily', self::ref('member:2'), 11);
        $store->save(new StreakState('daily', self::ref('member:1'), 1, 1, 10, 10, 'UTC'));
        $store->save(new StreakState('daily', self::ref('member:2'), 1, 1, 10, 10, 'UTC'));

        self::assertSame(4, $store->eraseMember(self::ref('member:1')));
        self::assertSame([], $store->units('daily', self::ref('member:1')));
        self::assertSame([], $store->frozen('daily', self::ref('member:1')));
        self::assertNull($store->state('daily', self::ref('member:1')));
        self::assertSame([10], $store->units('daily', self::ref('member:2')));
        self::assertSame([11], $store->frozen('daily', self::ref('member:2')));
        self::assertNotNull($store->state('daily', self::ref('member:2')));
        self::assertSame(0, $store->eraseMember(self::ref('member:1')));
    }

    protected static function gesture(string $streak, string $member, string $activity, int $unit, string $at): StreakGesture
    {
        return new StreakGesture($streak, self::ref($member), $activity, $unit, new DateTimeImmutable($at.' UTC'));
    }

    protected static function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }

    /**
     * @param list<StreakState> $states
     *
     * @return list<StreakState>
     */
    private function sorted(array $states): array
    {
        usort($states, static fn (StreakState $a, StreakState $b): int => $a->member->toString() <=> $b->member->toString());

        return $states;
    }
}
