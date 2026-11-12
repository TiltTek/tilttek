<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Leaderboards\Placing;
use Tilttek\Leaderboards\Snapshot;
use Tilttek\Leaderboards\Store\SnapshotStore;

/**
 * The contract every snapshot store passes: extend it with the store to test.
 */
abstract class SnapshotStoreTestCase extends TestCase
{
    abstract protected function store(): SnapshotStore;

    public function testASnapshotIsKeptOnce(): void
    {
        $store = $this->store();
        $snapshot = self::snapshot('games', '2026-10', ['member:1' => 9, 'member:2' => 7]);

        self::assertNull($store->find('games', '2026-10'));
        self::assertTrue($store->save($snapshot));
        self::assertFalse($store->save(self::snapshot('games', '2026-10', ['member:3' => 1])));
        self::assertTrue($store->save(self::snapshot('games', '2026-09', [])));
        self::assertTrue($store->save(self::snapshot('arcade', '2026-10', ['member:3' => 1])));

        self::assertEquals($snapshot, $store->find('games', '2026-10'));
        self::assertEquals(self::snapshot('games', '2026-09', []), $store->find('games', '2026-09'));
        self::assertNull($store->find('games', '2026-08'));
    }

    public function testThePlacingsOfAMember(): void
    {
        $store = $this->store();
        $store->save(self::snapshot('games', '2026-10', ['member:1' => 9, 'member:2' => 7]));
        $store->save(self::snapshot('games', '2026-09', ['member:2' => 4, 'member:1' => 3]));
        $store->save(self::snapshot('arcade', '2026-10', ['member:1' => 1]));

        self::assertSame(
            ['arcade 2026-10 1', 'games 2026-09 2', 'games 2026-10 1'],
            array_map(static fn (Placing $placing): string => $placing->board.' '.$placing->period.' '.$placing->rank, $store->placings(ResourceReference::fromString('member:1'))),
        );
        self::assertEquals(new Placing('games', '2026-09', ResourceReference::fromString('member:1'), 2, 3, new DateTimeImmutable('2026-11-01 00:00 UTC')), $store->placings(ResourceReference::fromString('member:1'))[1]);
    }

    public function testAMemberIsErasedFromTheSnapshots(): void
    {
        $store = $this->store();
        $store->save(self::snapshot('games', '2026-10', ['member:1' => 9, 'member:2' => 7, 'member:3' => 5]));
        $store->save(self::snapshot('arcade', '2026-10', ['member:2' => 1]));

        self::assertSame(2, $store->eraseMember(ResourceReference::fromString('member:2')));
        self::assertSame([], $store->placings(ResourceReference::fromString('member:2')));
        self::assertSame([1, 3], array_map(static fn (Placing $placing): int => $placing->rank, $store->find('games', '2026-10')->placings ?? []));
        self::assertSame(3, $store->find('games', '2026-10')?->participants);
        self::assertSame([], $store->find('arcade', '2026-10')->placings ?? null);
        self::assertSame(0, $store->eraseMember(ResourceReference::fromString('member:2')));
    }

    /**
     * @param array<string, int> $values the members and their scores, in rank order
     */
    protected static function snapshot(string $board, string $period, array $values): Snapshot
    {
        $placings = [];

        foreach ($values as $member => $value) {
            $placings[] = new Placing($board, $period, ResourceReference::fromString($member), \count($placings) + 1, $value, new DateTimeImmutable('2026-11-01 00:00 UTC'));
        }

        return new Snapshot($board, $period, new DateTimeImmutable('2026-11-01 00:05 UTC'), \count($values), $placings);
    }
}
