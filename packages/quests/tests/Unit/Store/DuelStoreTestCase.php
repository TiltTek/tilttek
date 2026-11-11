<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Quests\Duel\Duel;
use Tilttek\Quests\Duel\DuelStatus;
use Tilttek\Quests\Store\DuelStore;

/**
 * The contract every duel store passes: extend it with the store to test.
 */
abstract class DuelStoreTestCase extends TestCase
{
    abstract protected function store(): DuelStore;

    public function testADuelIsKeptByKey(): void
    {
        $store = $this->store();
        $offered = self::duel('d2', 'race', 'member:1', 'member:2', '2026-11-01 10:00');

        self::assertNull($store->find('d2'));
        $store->save($offered);
        self::assertEquals($offered, $store->find('d2'));

        $accepted = $offered->accepted(new DateTimeImmutable('2026-11-01 11:00 UTC'), new DateTimeImmutable('2026-11-03 11:00 UTC'));
        $store->save($accepted);
        $store->save(self::duel('d1', 'race', 'member:3', 'member:1', '2026-11-01 09:00')->accepted(new DateTimeImmutable('2026-11-01 12:00 UTC'), null));
        $store->save(self::duel('d3', 'other', 'member:1', 'member:2', '2026-11-01 08:00')->accepted(new DateTimeImmutable('2026-11-01 12:00 UTC'), null));
        $store->save(self::duel('d4', 'race', 'member:2', 'member:3', '2026-11-01 08:00')->accepted(new DateTimeImmutable('2026-11-01 12:00 UTC'), null));
        $store->save(self::duel('d5', 'race', 'member:1', 'member:4', '2026-11-01 07:00'));

        $won = self::duel('d6', 'race', 'member:4', 'member:1', '2026-11-01 07:00')->accepted(new DateTimeImmutable('2026-11-01 08:00 UTC'), null)
            ->with(DuelStatus::Won, new DateTimeImmutable('2026-11-01 09:00 UTC'), ResourceReference::fromString('member:1'));
        $store->save($won);

        self::assertEquals($accepted, $store->find('d2'));
        self::assertEquals($won, $store->find('d6'));
        self::assertSame(['d1', 'd2'], $this->keys($store->accepted('race', ResourceReference::fromString('member:1'))));
        self::assertSame(['d5', 'd6', 'd3', 'd1', 'd2'], $this->keys($store->ofMember(ResourceReference::fromString('member:1'))));
    }

    public function testTheDuelsWhoseTimeIsUpAreDue(): void
    {
        $store = $this->store();
        $store->save(self::duel('d1', 'race', 'member:1', 'member:2', '2026-11-01 10:00'));
        $store->save(self::duel('d2', 'race', 'member:1', 'member:2', '2026-11-01 11:00'));
        $store->save(self::duel('d3', 'race', 'member:1', 'member:2', '2026-11-01 09:00')->accepted(new DateTimeImmutable('2026-11-01 10:00 UTC'), new DateTimeImmutable('2026-11-05 10:00 UTC')));
        $store->save(self::duel('d4', 'race', 'member:1', 'member:2', '2026-11-01 09:00')->accepted(new DateTimeImmutable('2026-11-01 10:00 UTC'), new DateTimeImmutable('2026-11-05 10:01 UTC')));
        $store->save(self::duel('d5', 'race', 'member:1', 'member:2', '2026-11-01 09:00')->accepted(new DateTimeImmutable('2026-11-01 10:00 UTC'), null));
        $store->save(self::duel('d6', 'race', 'member:1', 'member:2', '2026-11-01 09:00')->with(DuelStatus::Declined, new DateTimeImmutable('2026-11-01 09:30 UTC')));

        self::assertSame(['d1', 'd3'], $this->keys($store->due(new DateTimeImmutable('2026-11-01 10:00 UTC'), new DateTimeImmutable('2026-11-05 10:00 UTC'))));
    }

    public function testTheDuelsWonAreAcceptedAgainBeforeARebuild(): void
    {
        $store = $this->store();
        $accepted = self::duel('d1', 'race', 'member:1', 'member:2', '2026-11-01 09:00')->accepted(new DateTimeImmutable('2026-11-01 10:00 UTC'), null);
        $early = $accepted->with(DuelStatus::Won, new DateTimeImmutable('2026-11-01 11:00 UTC'), ResourceReference::fromString('member:1'));
        $late = self::duel('d2', 'race', 'member:1', 'member:2', '2026-11-01 09:00')->accepted(new DateTimeImmutable('2026-11-01 10:00 UTC'), null)
            ->with(DuelStatus::Won, new DateTimeImmutable('2026-11-02 11:00 UTC'), ResourceReference::fromString('member:2'));
        $earlier = self::duel('d0', 'race', 'member:3', 'member:2', '2026-11-01 08:00')->accepted(new DateTimeImmutable('2026-11-01 08:30 UTC'), null)
            ->with(DuelStatus::Won, new DateTimeImmutable('2026-11-01 09:00 UTC'), ResourceReference::fromString('member:3'));
        $store->save($early);
        $store->save($earlier);
        $store->save($late);
        $store->save(self::duel('d3', 'race', 'member:1', 'member:2', '2026-11-03 09:00'));

        self::assertEquals([$late], $store->reopen(new DateTimeImmutable('2026-11-02 11:00 UTC')));
        self::assertEquals($late->reopened(), $store->find('d2'));
        self::assertEquals([$earlier, $early], $store->reopen(null));
        self::assertEquals($accepted, $store->find('d1'));
        self::assertSame(DuelStatus::Offered, $store->find('d3')?->status);
    }

    public function testAMemberIsErased(): void
    {
        $store = $this->store();
        $store->save(self::duel('d1', 'race', 'member:1', 'member:2', '2026-11-01 09:00'));
        $store->save(self::duel('d2', 'race', 'member:3', 'member:1', '2026-11-01 09:00'));
        $store->save(self::duel('d3', 'race', 'member:2', 'member:3', '2026-11-01 09:00'));

        self::assertSame(2, $store->eraseMember(ResourceReference::fromString('member:1')));
        self::assertNull($store->find('d1'));
        self::assertNotNull($store->find('d3'));
    }

    protected static function duel(string $key, string $quest, string $challenger, string $opponent, string $at): Duel
    {
        return new Duel($key, $quest, ResourceReference::fromString($challenger), ResourceReference::fromString($opponent), new DateTimeImmutable($at.' UTC'));
    }

    /**
     * @param list<Duel> $duels
     *
     * @return list<string>
     */
    private function keys(array $duels): array
    {
        return array_map(static fn (Duel $duel): string => $duel->key, $duels);
    }
}
