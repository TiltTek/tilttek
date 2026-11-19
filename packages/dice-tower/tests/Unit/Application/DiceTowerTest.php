<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Application;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RoundingMode;
use Tilttek\DiceBundle\Application\Exception\UnknownRoll;
use Tilttek\DiceTower\Application\DiceTower;
use Tilttek\DiceTower\Application\NoTowerPublisher;
use Tilttek\DiceTower\Application\TowerEntry;
use Tilttek\DiceTower\Application\TowerRelays;
use Tilttek\DiceTower\Tests\Unit\Fixture\RecordingPublisher;
use Tilttek\DiceTower\Tests\Unit\Fixture\RecordingRelay;
use Tilttek\DiceTower\Tests\Unit\Fixture\Tower;

#[CoversClass(DiceTower::class)]
#[CoversClass(TowerEntry::class)]
#[CoversClass(NoTowerPublisher::class)]
final class DiceTowerTest extends TestCase
{
    public function testARollIsKeptInTheJournalDroppedInTheTowerPublishedAndRelayed(): void
    {
        $publisher = new RecordingPublisher();
        $relay = new RecordingRelay();
        $tower = new Tower($publisher, new TowerRelays([$relay]));

        $entry = $tower->tower->roll(Tower::table(), '2d6+1', Tower::member(), rounding: RoundingMode::PositiveInfinity);
        $recorded = $tower->rolls->get($entry->roll);

        self::assertNotNull($recorded);
        self::assertSame('member:1', $recorded->owner()?->toString());
        self::assertSame(RoundingMode::PositiveInfinity, $recorded->rounding());
        self::assertSame([1, 'table:7', 'member:1'], [$entry->position, $entry->table, $entry->roller]);
        self::assertSame([$entry], $publisher->entries);
        self::assertSame([$entry], $relay->entries);
        self::assertSame($recorded->result()[0]['total'], $entry->totals()[0]);
    }

    public function testAnExpressionIsReadInTheRollersNotation(): void
    {
        $entry = new Tower()->tower->roll(Tower::table(), '1W6', locale: 'de');

        self::assertSame(['1d6', null], [$entry->results[0]['expression'], $entry->roller]);
    }

    public function testARollOfTheJournalIsDroppedByItsIdentifier(): void
    {
        $tower = new Tower();
        $roll = $tower->dice->rollReplayable('1d20', Tower::member('2'));
        $tower->clock->sleep(60);

        $entry = $tower->tower->drop(Tower::table(), $roll->id());

        self::assertSame([1, $roll->id(), null], [$entry->position, $entry->roll, $entry->roller]);
        self::assertSame('2026-11-12 20:00:00', $entry->rolledAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-12 20:01:00', $tower->drops->at(Tower::table(), 1)?->droppedAt()->format('Y-m-d H:i:s'));
        self::assertSame('member:3', $tower->tower->drop(Tower::table(), $roll->id(), Tower::member('3'))->roller);
    }

    public function testARollTheJournalDoesNotHoldIsRefused(): void
    {
        $publisher = new RecordingPublisher();
        $tower = new Tower($publisher);

        $this->expectException(UnknownRoll::class);

        try {
            $tower->tower->drop(Tower::table(), 'ffffffffffffffffffffffffffffffff');
        } finally {
            self::assertSame([], $publisher->entries);
            self::assertSame([], $tower->drops->latest(Tower::table(), 10));
        }
    }

    public function testTheEntriesAreReadFromTheLogInOrderAsManyAsTheHistory(): void
    {
        $tower = new Tower();

        foreach (['1d4', '1d6', '1d8', '1d10', '1d12'] as $expression) {
            $tower->tower->roll(Tower::table(), $expression);
            $tower->tower->roll(Tower::table('8'), $expression);
        }

        self::assertSame([5, 7, 9], $this->positions($tower->tower->entries(Tower::table())));
        self::assertSame(['1d8', '1d10', '1d12'], array_map(static fn (TowerEntry $entry): string => $entry->results[0]['expression'], $tower->tower->entries(Tower::table())));
        self::assertSame([1, 3, 5], $this->positions($tower->tower->entries(Tower::table(), 0)));
        self::assertSame([7, 9], $this->positions($tower->tower->entries(Tower::table(), 5)));
        self::assertSame([], $tower->tower->entries(Tower::table(), 9));
        self::assertSame([], $tower->tower->entries(Tower::table('9')));
        self::assertSame(3, $tower->tower->entry(Tower::table(), 3)?->position);
        self::assertNull($tower->tower->entry(Tower::table(), 4));
    }

    public function testARollErasedFromTheJournalIsLeftOut(): void
    {
        $tower = new Tower();
        $tower->tower->roll(Tower::table(), '1d6', Tower::member());
        $tower->tower->roll(Tower::table(), '1d6', Tower::member('2'));
        $tower->dice->eraseOwner(Tower::member());

        self::assertSame([2], $this->positions($tower->tower->entries(Tower::table())));
        self::assertNull($tower->tower->entry(Tower::table(), 1));
        self::assertSame(1, $tower->tower->eraseRoller(Tower::member()));
        self::assertNull($tower->drops->at(Tower::table(), 1));
    }

    #[TestWith([0])]
    #[TestWith([201])]
    public function testTheHistoryIsBounded(int $history): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessageIs(\sprintf('A tower shows 1 to 200 entries at once; %d is out of range.', $history));

        new Tower(history: $history);
    }

    #[TestWith([1])]
    #[TestWith([200])]
    public function testTheBoundsOfTheHistoryAreAllowed(int $history): void
    {
        $tower = new Tower(history: $history);
        $tower->tower->roll(Tower::table(), '1d6');
        $tower->tower->roll(Tower::table(), '1d6');

        self::assertCount(min(2, $history), $tower->tower->entries(Tower::table()));
    }

    public function testWithoutPublisherNorRelayTheRollIsDroppedAlone(): void
    {
        $tower = new Tower();
        $tower = new DiceTower($tower->drops, $tower->rolls, $tower->dice, $tower->clock);
        new NoTowerPublisher()->publish($tower->roll(Tower::table(), '1d6'));

        self::assertCount(1, $tower->entries(Tower::table()));
        self::assertCount(1, $tower->entries(Tower::table(), 0));
    }

    /**
     * @param list<TowerEntry> $entries
     *
     * @return list<int>
     */
    private function positions(array $entries): array
    {
        return array_map(static fn (TowerEntry $entry): int => $entry->position, $entries);
    }
}
