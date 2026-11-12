<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Leaderboards\League\Membership;
use Tilttek\Leaderboards\League\Movement;
use Tilttek\Leaderboards\League\Settlement;
use Tilttek\Leaderboards\Store\LeagueStore;

/**
 * The contract every league store passes: extend it with the store to test.
 */
abstract class LeagueStoreTestCase extends TestCase
{
    abstract protected function store(): LeagueStore;

    public function testDivisionsFillInTheOrderOfArrival(): void
    {
        $store = $this->store();

        foreach (['member:1', 'member:2', 'member:3', 'member:4', 'member:5'] as $minute => $member) {
            $store->join('xp', '2026-w45', $this->member($member), 'bronze', $this->at($minute), 2);
        }

        $store->join('xp', '2026-w45', $this->member('member:6'), 'silver', $this->at(9), 2);
        $store->join('xp', '2026-w46', $this->member('member:7'), 'bronze', $this->at(9), 2);
        $store->join('other', '2026-w45', $this->member('member:8'), 'bronze', $this->at(9), 2);

        self::assertSame(
            ['bronze 1 member:1', 'bronze 1 member:2', 'bronze 2 member:3', 'bronze 2 member:4', 'bronze 3 member:5', 'silver 1 member:6'],
            array_map($this->line(...), $store->memberships('xp', '2026-w45')),
        );
        self::assertSame(['bronze 2 member:3', 'bronze 2 member:4'], array_map($this->line(...), $store->division('xp', '2026-w45', 'bronze', 2)));
        self::assertSame([], $store->division('xp', '2026-w45', 'bronze', 4));
        self::assertSame(['bronze 1 member:7'], array_map($this->line(...), $store->memberships('xp', '2026-w46')));
        self::assertEquals(new Membership('xp', '2026-w45', $this->member('member:3'), 'bronze', 2, $this->at(2)), $store->membership('xp', '2026-w45', $this->member('member:3')));
        self::assertNull($store->membership('xp', '2026-w46', $this->member('member:3')));
        self::assertNull($store->membership('other', '2026-w45', $this->member('member:3')));
    }

    public function testDivisionsHoldOneCohortEach(): void
    {
        $store = $this->store();

        self::assertSame(1, $store->join('xp', '2026-w45', self::member('member:1'), 'bronze', self::at(0), 2, 3)->division);
        self::assertSame(2, $store->join('xp', '2026-w45', self::member('member:2'), 'bronze', self::at(1), 2)->division);
        self::assertSame(1, $store->join('xp', '2026-w45', self::member('member:3'), 'bronze', self::at(2), 2, 3)->division);
        self::assertSame(3, $store->join('xp', '2026-w45', self::member('member:4'), 'bronze', self::at(3), 2, 3)->division);
        self::assertSame(2, $store->join('xp', '2026-w45', self::member('member:5'), 'bronze', self::at(4), 2)->division);
        self::assertSame(1, $store->join('xp', '2026-w45', self::member('member:6'), 'silver', self::at(5), 2, 3)->division);
        self::assertSame(4, $store->join('xp', '2026-w45', self::member('member:7'), 'bronze', self::at(6), 2, 1)->division);
        self::assertSame(3, $store->membership('xp', '2026-w45', self::member('member:4'))?->cohort);
        self::assertSame(0, $store->membership('xp', '2026-w45', self::member('member:5'))?->cohort);
        self::assertSame([3, 3], array_map(static fn (Membership $membership): int => $membership->cohort, $store->division('xp', '2026-w45', 'bronze', 1)));
    }

    public function testADivisionOfOneHoldsOneMember(): void
    {
        $store = $this->store();

        self::assertSame(1, $store->join('xp', '2026-w45', self::member('member:1'), 'bronze', self::at(0), 1)->division);
        self::assertSame(2, $store->join('xp', '2026-w45', self::member('member:2'), 'bronze', self::at(1), 1)->division);
        self::assertSame(1, $store->join('xp', '2026-w45', self::member('member:3'), 'silver', self::at(2), 1)->division);
    }

    public function testAMembershipSettledWithoutAMovementKeepsNone(): void
    {
        $store = $this->store();
        $membership = $store->join('xp', '2026-w45', self::member('member:1'), 'bronze', self::at(0), 30);

        self::assertTrue($store->settle(new Settlement('xp', '2026-w45', self::at(60), [$membership]), []));
        self::assertNull($store->membership('xp', '2026-w45', self::member('member:1'))?->movement);
        self::assertTrue($store->isSettled('xp', '2026-w45'));
    }

    public function testAMemberJoinsAWeekOnce(): void
    {
        $store = $this->store();
        $first = $store->join('xp', '2026-w45', $this->member('member:1'), 'bronze', $this->at(0), 30);

        self::assertEquals($first, $store->join('xp', '2026-w45', $this->member('member:1'), 'gold', $this->at(5), 30));
        self::assertCount(1, $store->memberships('xp', '2026-w45'));
    }

    public function testALeftPlaceIsFilledAgain(): void
    {
        $store = $this->store();
        $store->join('xp', '2026-w45', $this->member('member:1'), 'bronze', $this->at(0), 2);
        $store->join('xp', '2026-w45', $this->member('member:2'), 'bronze', $this->at(1), 2);
        $store->join('xp', '2026-w45', $this->member('member:3'), 'bronze', $this->at(2), 2);
        $store->eraseMember($this->member('member:1'));

        self::assertSame(1, $store->join('xp', '2026-w45', $this->member('member:4'), 'bronze', $this->at(3), 2)->division);
    }

    public function testMembersOfOneMomentAreOrderedByReference(): void
    {
        $store = $this->store();
        $store->join('xp', '2026-w45', $this->member('member:b'), 'bronze', $this->at(0), 30);
        $store->join('xp', '2026-w45', $this->member('member:a'), 'bronze', $this->at(0), 30);
        $store->join('xp', '2026-w45', $this->member('member:c'), 'bronze', $this->at(-1), 30);

        self::assertSame(['bronze 1 member:c', 'bronze 1 member:a', 'bronze 1 member:b'], array_map($this->line(...), $store->division('xp', '2026-w45', 'bronze', 1)));
    }

    public function testAWeekIsSettledOnce(): void
    {
        $store = $this->store();
        $one = $store->join('xp', '2026-w45', $this->member('member:1'), 'bronze', $this->at(0), 30);
        $two = $store->join('xp', '2026-w45', $this->member('member:2'), 'bronze', $this->at(1), 30);
        $store->join('xp', '2026-w46', $this->member('member:1'), 'bronze', $this->at(9), 30);
        $store->join('xp', '2026-w44', $this->member('member:3'), 'bronze', $this->at(9), 30);
        $store->join('other', '2026-w43', $this->member('member:3'), 'bronze', $this->at(9), 30);

        self::assertNull($store->tier('xp', $this->member('member:1')));
        self::assertFalse($store->isSettled('xp', '2026-w45'));
        self::assertSame(['2026-w44', '2026-w45', '2026-w46'], $store->unsettled('xp'));

        $settlement = new Settlement('xp', '2026-w45', $this->at(60), [$one->settled(Movement::Promoted), $two->settled(Movement::Stayed)]);

        self::assertTrue($store->settle($settlement, ['member:1' => 'silver', 'member:2' => 'bronze']));
        self::assertTrue($store->isSettled('xp', '2026-w45'));
        self::assertFalse($store->isSettled('xp', '2026-w46'));
        self::assertFalse($store->isSettled('other', '2026-w45'));
        self::assertSame(['2026-w44', '2026-w46'], $store->unsettled('xp'));
        self::assertSame(['2026-w43'], $store->unsettled('other'));
        self::assertSame('silver', $store->tier('xp', $this->member('member:1')));
        self::assertSame('bronze', $store->tier('xp', $this->member('member:2')));
        self::assertNull($store->tier('other', $this->member('member:1')));
        self::assertSame(Movement::Promoted, $store->membership('xp', '2026-w45', $this->member('member:1'))?->movement);
        self::assertSame(Movement::Stayed, $store->membership('xp', '2026-w45', $this->member('member:2'))?->movement);
        self::assertNull($store->membership('xp', '2026-w46', $this->member('member:1'))?->movement);

        self::assertFalse($store->settle(new Settlement('xp', '2026-w45', $this->at(61), [$one->settled(Movement::Relegated)]), ['member:1' => 'iron']));
        self::assertSame('silver', $store->tier('xp', $this->member('member:1')));
        self::assertSame(Movement::Promoted, $store->membership('xp', '2026-w45', $this->member('member:1'))?->movement);

        self::assertTrue($store->settle(new Settlement('xp', '2026-w46', $this->at(70), []), ['member:1' => 'gold']));
        self::assertSame('gold', $store->tier('xp', $this->member('member:1')));
    }

    public function testAMemberIsExportedAndErased(): void
    {
        $store = $this->store();
        $store->join('xp', '2026-w46', $this->member('member:1'), 'silver', $this->at(9), 30);

        $one = $store->join('xp', '2026-w45', $this->member('member:1'), 'bronze', $this->at(0), 30);
        $store->join('arcade', '2026-w45', $this->member('member:1'), 'bronze', $this->at(0), 30);
        $two = $store->join('xp', '2026-w45', $this->member('member:2'), 'bronze', $this->at(1), 30);
        $store->settle(new Settlement('xp', '2026-w45', $this->at(60), [$one->settled(Movement::Promoted), $two->settled(Movement::Stayed)]), ['member:1' => 'silver', 'member:2' => 'bronze']);
        $store->settle(new Settlement('arcade', '2026-w45', $this->at(60), []), ['member:1' => 'gold']);

        self::assertSame(['arcade' => 'gold', 'xp' => 'silver'], $store->tiersOf($this->member('member:1')));
        self::assertSame(['arcade 2026-w45', 'xp 2026-w45', 'xp 2026-w46'], array_map(static fn (Membership $membership): string => $membership->board.' '.$membership->week, $store->membershipsOf($this->member('member:1'))));
        self::assertSame(Movement::Promoted, $store->membershipsOf($this->member('member:1'))[1]->movement);
        self::assertSame([], $store->tiersOf($this->member('member:9')));

        self::assertSame(5, $store->eraseMember($this->member('member:1')));
        self::assertSame([], $store->tiersOf($this->member('member:1')));
        self::assertSame([], $store->membershipsOf($this->member('member:1')));
        self::assertSame(['bronze 1 member:2'], array_map($this->line(...), $store->memberships('xp', '2026-w45')));
        self::assertSame('bronze', $store->tier('xp', $this->member('member:2')));
        self::assertTrue($store->isSettled('xp', '2026-w45'));
        self::assertSame(0, $store->eraseMember($this->member('member:1')));
    }

    private function member(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }

    private function at(int $minutes): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-03 12:00 UTC')->modify(\sprintf('%+d minutes', $minutes));
    }

    private function line(Membership $membership): string
    {
        return $membership->tier.' '.$membership->division.' '.$membership->member->toString();
    }
}
