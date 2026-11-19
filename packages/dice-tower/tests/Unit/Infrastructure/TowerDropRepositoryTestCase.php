<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceTower\Domain\Contract\TowerDropRepository;
use Tilttek\DiceTower\Domain\Model\TowerDrop;
use Tilttek\DiceTower\Tests\Unit\Fixture\Tower;

/**
 * What every log of the towers does: give each drop a greater position than the last, read a table's drops after a
 * position or its last ones in order, find one, and erase a roller's.
 */
abstract class TowerDropRepositoryTestCase extends TestCase
{
    public function testEachDropIsKeptAfterTheLastOne(): void
    {
        $repository = $this->repository();
        $first = $repository->append(Tower::table(), 'aaaa', Tower::member(), new DateTimeImmutable('2026-11-12 20:00:00'));
        $second = $repository->append(Tower::table('8'), 'bbbb', null, new DateTimeImmutable('2026-11-12 20:01:00'));
        $this->clear();

        self::assertGreaterThan($first->position(), $second->position());
        $found = $repository->at(Tower::table(), $first->position());
        self::assertNotNull($found);
        self::assertSame([$first->position(), 'table:7', 'aaaa', 'member:1', '2026-11-12 20:00:00'], [$found->position(), $found->table()->toString(), $found->roll(), $found->roller()?->toString(), $found->droppedAt()->format('Y-m-d H:i:s')]);
        self::assertNull($repository->at(Tower::table(), $second->position()));
        self::assertNull($repository->at(Tower::table('8'), $second->position())?->roller());
    }

    public function testATablesDropsAreReadInOrder(): void
    {
        $repository = $this->repository();
        $positions = [];

        foreach (range(1, 6) as $index) {
            $positions[] = $repository->append(Tower::table(), \sprintf('roll%d', $index), null, new DateTimeImmutable('2026-11-12 20:00:00'))->position();
            $repository->append(Tower::table('8'), 'other', null, new DateTimeImmutable('2026-11-12 20:00:00'));
        }

        $this->clear();

        self::assertSame(['roll4', 'roll5', 'roll6'], $this->rolls($repository->latest(Tower::table(), 3)));
        self::assertSame(['roll1', 'roll2', 'roll3', 'roll4', 'roll5', 'roll6'], $this->rolls($repository->latest(Tower::table(), 10)));
        self::assertSame(['roll3', 'roll4'], $this->rolls($repository->after(Tower::table(), $positions[1], 2)));
        self::assertSame(['roll6'], $this->rolls($repository->after(Tower::table(), $positions[4], 5)));
        self::assertSame([], $repository->after(Tower::table(), $positions[5], 5));
        self::assertSame([], $repository->latest(Tower::table('9'), 5));
    }

    public function testARollersDropsAreErased(): void
    {
        $repository = $this->repository();
        $repository->append(Tower::table(), 'mine', Tower::member(), new DateTimeImmutable('2026-11-12 20:00:00'));
        $repository->append(Tower::table('8'), 'mine too', Tower::member(), new DateTimeImmutable('2026-11-12 20:00:00'));
        $repository->append(Tower::table(), 'theirs', Tower::member('2'), new DateTimeImmutable('2026-11-12 20:00:00'));
        $repository->append(Tower::table(), 'nobody', null, new DateTimeImmutable('2026-11-12 20:00:00'));

        self::assertSame(2, $repository->eraseRoller(Tower::member()));
        $this->clear();
        self::assertSame(['theirs', 'nobody'], $this->rolls($repository->latest(Tower::table(), 10)));
        self::assertSame([], $repository->latest(Tower::table('8'), 10));
        self::assertSame(0, $repository->eraseRoller(Tower::member()));
    }

    abstract protected function repository(): TowerDropRepository;

    protected function clear(): void
    {
    }

    /**
     * @param list<TowerDrop> $drops
     *
     * @return list<string>
     */
    private function rolls(array $drops): array
    {
        return array_map(static fn (TowerDrop $drop): string => $drop->roll(), $drops);
    }
}
